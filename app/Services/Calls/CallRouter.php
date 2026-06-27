<?php

namespace App\Services\Calls;

use App\Enums\CallEventType;
use App\Enums\CallStatus;
use App\Enums\CampaignChannel;
use App\Enums\DeliveryMethod;
use App\Enums\RoutingMode;
use App\Models\CallSession;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Services\Billing\RevenueCalculator;
use App\Services\Caps\CapService;
use App\Services\Rules\RuleEngine;
use App\Services\Scheduling\ScheduleService;
use App\Services\Telephony\CallTwimlContext;
use App\Services\Telephony\TelephonyManager;
use Illuminate\Support\Collection;

class CallRouter
{
    public function __construct(
        protected CallPingService $pingService,
        protected CallBillingService $billingService,
        protected CallEventLogger $logger,
        protected IvrEngine $ivrEngine,
        protected TelephonyManager $telephony,
        protected CapService $capService,
        protected ScheduleService $scheduleService,
        protected RuleEngine $ruleEngine,
        protected RevenueCalculator $revenueCalculator,
        protected CallHybridService $hybridService,
    ) {}

    public function route(CallSession $session): CallRoutingResult
    {
        $session->update(['status' => CallStatus::Routing]);
        $campaign = $session->campaign;

        if (! $campaign) {
            return $this->fail($session, 'No campaign configured');
        }

        $deliveries = $this->eligibleDeliveries($session, $campaign);

        if ($deliveries->isEmpty()) {
            return $this->handleUnsold($session, $campaign);
        }

        $routingMode = $campaign->call_settings['routing_mode'] ?? 'waterfall';

        return match ($routingMode) {
            'parallel_auction' => $this->routeAuction($session, $deliveries),
            default => $this->routeWaterfall($session, $deliveries),
        };
    }

    protected function routeWaterfall(CallSession $session, Collection $deliveries): CallRoutingResult
    {
        foreach ($deliveries as $delivery) {
            $result = $this->attemptDelivery($session, $delivery);

            if ($result->success) {
                return $result;
            }
        }

        return $this->handleUnsold($session, $session->campaign);
    }

    protected function routeAuction(CallSession $session, Collection $deliveries): CallRoutingResult
    {
        $pings = [];

        foreach ($deliveries as $delivery) {
            if ($delivery->method === DeliveryMethod::CallPingPost) {
                $ping = $this->pingService->ping($session, $delivery);
                if ($ping['accepted']) {
                    $pings[] = ['delivery' => $delivery, 'revenue' => $ping['revenue']];
                }
            } elseif ($this->pingService->destinationPhone($delivery)) {
                $revenue = (float) $this->revenueCalculator->calculate($delivery, $session->callAttributes());
                $pings[] = ['delivery' => $delivery, 'revenue' => $revenue];
            }
        }

        if (empty($pings)) {
            return $this->handleUnsold($session, $session->campaign);
        }

        usort($pings, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);
        $winner = $pings[0]['delivery'];

        return $this->transferToDelivery($session, $winner, $pings[0]['revenue']);
    }

    protected function attemptDelivery(CallSession $session, Delivery $delivery): CallRoutingResult
    {
        $revenue = 0;

        if ($delivery->method === DeliveryMethod::CallPingPost) {
            $this->logger->log($session, CallEventType::PingSent, 'Call ping sent', ['delivery_id' => $delivery->id]);
            $ping = $this->pingService->ping($session, $delivery);

            if (! $ping['accepted']) {
                $this->logger->log($session, CallEventType::PingRejected, 'Call ping rejected', ['delivery_id' => $delivery->id]);

                return CallRoutingResult::failed('ping_rejected');
            }

            $this->logger->log($session, CallEventType::PingAccepted, 'Call ping accepted', [
                'delivery_id' => $delivery->id,
                'revenue' => $ping['revenue'],
            ]);
            $revenue = $ping['revenue'];
        } else {
            $revenue = (float) $this->revenueCalculator->calculate($delivery, $session->callAttributes());
        }

        return $this->transferToDelivery($session, $delivery, $revenue);
    }

    protected function transferToDelivery(CallSession $session, Delivery $delivery, float $revenue): CallRoutingResult
    {
        $destination = $this->pingService->destinationPhone($delivery);

        if (! $destination) {
            return CallRoutingResult::failed('missing_destination');
        }

        $buyer = $delivery->buyer;

        if ($buyer && ! $this->billingService->chargeForCall($session, $buyer, $revenue)) {
            return CallRoutingResult::failed('insufficient_credit');
        }

        $session->update([
            'status' => CallStatus::Transferring,
            'sold_to_buyer_id' => $delivery->buyer_id,
            'winning_delivery_id' => $delivery->id,
            'revenue' => $revenue,
            'transferred_at' => now(),
        ]);

        $this->logger->log($session, CallEventType::Transfer, 'Transferring call', [
            'destination' => $destination,
            'delivery_id' => $delivery->id,
            'revenue' => $revenue,
        ]);

        if ($session->provider_call_sid) {
            $record = $session->campaign?->call_settings['recording_enabled'] ?? config('telephony.recording_enabled');
            $this->telephony->gateway($session->trackingNumber?->provider)->transferCall(
                $session->provider_call_sid,
                $destination,
                [
                    'record' => $record,
                    'whisper' => $delivery->config['whisper_url'] ?? null,
                    'caller_id' => $delivery->config['caller_id'] ?? $session->caller_number,
                ],
            );
        }

        return CallRoutingResult::success($destination, $revenue, $delivery);
    }

    protected function handleUnsold(CallSession $session, Campaign $campaign): CallRoutingResult
    {
        $session->update(['status' => CallStatus::Unsold, 'completed_at' => now()]);

        if ($campaign->channel === CampaignChannel::Hybrid) {
            $lead = $this->hybridService->createLeadFromCall($session);

            if ($lead) {
                $this->logger->log($session, CallEventType::HybridFallback, 'Hybrid fallback to lead pipeline', [
                    'lead_uuid' => $lead->uuid,
                ]);

                return CallRoutingResult::hybridFallback($lead);
            }
        }

        return CallRoutingResult::unsold();
    }

    protected function fail(CallSession $session, string $reason): CallRoutingResult
    {
        $session->update(['status' => CallStatus::Failed, 'disposition' => $reason, 'completed_at' => now()]);
        $this->logger->log($session, CallEventType::Failed, $reason, [], 'error');

        return CallRoutingResult::failed($reason);
    }

    /**
     * @return Collection<int, Delivery>
     */
    protected function eligibleDeliveries(CallSession $session, Campaign $campaign): Collection
    {
        return $campaign->deliveries()
            ->where('status', 'active')
            ->whereIn('method', [
                DeliveryMethod::CallPingPost,
                DeliveryMethod::CallDirectTransfer,
                DeliveryMethod::CallWarmTransfer,
            ])
            ->orderBy('priority')
            ->get()
            ->filter(function (Delivery $delivery) use ($session) {
                if (! $this->scheduleService->isWithinSchedule($delivery->schedule)) {
                    return false;
                }

                if (! $this->capService->hasCapacity('delivery', $delivery->id, $delivery->caps)) {
                    return false;
                }

                if ($delivery->buyer_id && $delivery->buyer) {
                    if (($delivery->buyer->status ?? 'active') !== 'active') {
                        return false;
                    }

                    $concurrentCap = $delivery->buyer->settings['concurrent_call_cap'] ?? null;
                    if ($concurrentCap !== null) {
                        $active = CallSession::where('sold_to_buyer_id', $delivery->buyer_id)
                            ->whereIn('status', [CallStatus::Transferring, CallStatus::Connected])
                            ->count();

                        if ($active >= (int) $concurrentCap) {
                            return false;
                        }
                    }
                }

                if (! empty($delivery->eligibility_rules)) {
                    if ($this->ruleEngine->firstFailingCondition($delivery->eligibility_rules, $session->callAttributes()) !== null) {
                        return false;
                    }
                }

                return $this->pingService->isCallDelivery($delivery);
            });
    }

    public function buildTwiml(CallSession $session, string $webhookBase): string
    {
        $ivrResult = $this->ivrEngine->processStep($session);

        if ($ivrResult['route']) {
            $routing = $this->route($session);

            if ($routing->success && $routing->destination) {
                $record = $session->campaign?->call_settings['recording_enabled'] ?? false;

                return $this->telephony->gateway($session->trackingNumber?->provider)->buildInboundTwiml(
                    new CallTwimlContext(
                        session: $session,
                        actionUrl: $webhookBase.'/status',
                        message: $ivrResult['message'],
                        transferNumber: $routing->destination,
                        record: $record,
                    ),
                );
            }
        }

        $gatherUrl = $ivrResult['gather']
            ? $webhookBase.'/gather?session='.$session->uuid
            : null;

        return $this->telephony->gateway($session->trackingNumber?->provider)->buildInboundTwiml(
            new CallTwimlContext(
                session: $session,
                actionUrl: $webhookBase.'/status',
                message: $ivrResult['message'],
                gatherUrl: $gatherUrl,
            ),
        );
    }

    public function recordDisposition(CallSession $session, array $data): CallSession
    {
        $duration = (int) ($data['duration_seconds'] ?? $session->duration_seconds);
        $disposition = $data['disposition'] ?? 'connected';

        $minDuration = $session->min_duration_seconds
            ?: ($session->campaign?->call_settings['min_duration_seconds'] ?? config('telephony.default_min_duration_seconds', 60));
        $billableSeconds = $duration >= $minDuration ? $duration : 0;

        $session->update([
            'status' => CallStatus::Completed,
            'disposition' => $disposition,
            'duration_seconds' => $duration,
            'billable_seconds' => $billableSeconds,
            'completed_at' => now(),
        ]);

        $this->logger->log($session, CallEventType::Disposition, 'Call disposition recorded', $data);

        if (($session->campaign?->call_settings['recording_enabled'] ?? false) && $session->provider_call_sid) {
            $recordingSid = $this->telephony->gateway($session->trackingNumber?->provider)
                ->startRecording($session->provider_call_sid);

            if ($recordingSid) {
                $session->recordings()->create([
                    'provider_recording_sid' => $recordingSid,
                    'status' => 'processing',
                ]);
                $this->logger->log($session, CallEventType::Recording, 'Recording started', ['sid' => $recordingSid]);
            }
        }

        return $session->fresh();
    }
}
