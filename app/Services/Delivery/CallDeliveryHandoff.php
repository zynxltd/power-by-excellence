<?php

namespace App\Services\Delivery;

use App\Enums\DeliveryMethod;
use App\Models\CallSession;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\Lead;
use App\Services\Billing\RevenueCalculator;
use App\Services\Calls\CallPingService;
use Illuminate\Support\Facades\Http;

/**
 * Bridges HTTP delivery execution to call routing when a call session is present.
 */
class CallDeliveryHandoff
{
    public function __construct(
        protected CallPingService $callPingService,
        protected DeliveryPayloadBuilder $payloadBuilder,
        protected DeliveryResponseMatcher $responseMatcher,
        protected RevenueCalculator $revenueCalculator,
    ) {}

    public function execute(Lead $lead, Delivery $delivery, DeliveryLog $log, array $pingFields): DeliveryResult
    {
        $session = $this->resolveSession($lead);

        return match ($delivery->method) {
            DeliveryMethod::CallPingPost => $this->callPingPost($lead, $delivery, $log, $pingFields, $session),
            DeliveryMethod::CallDirectTransfer => $this->callDirectTransfer($lead, $delivery, $log, $session),
            DeliveryMethod::CallWarmTransfer => $this->callWarmTransfer($lead, $delivery, $log, $pingFields, $session),
            default => DeliveryResult::skipped('unsupported_call_method'),
        };
    }

    protected function callPingPost(Lead $lead, Delivery $delivery, DeliveryLog $log, array $pingFields, ?CallSession $session): DeliveryResult
    {
        if ($session) {
            $ping = $this->callPingService->ping($session, $delivery);

            if (! $ping['accepted']) {
                return DeliveryResult::skipped('call_ping_rejected');
            }

            $this->assignSession($session, $delivery, (float) $ping['revenue'], $this->callPingService->destinationPhone($delivery));

            $log->update([
                'ping_response' => $ping['response'],
                'revenue' => $ping['revenue'],
                'post_response' => ['handoff' => 'call_session', 'call_session_uuid' => $session->uuid],
            ]);

            return DeliveryResult::success((float) $ping['revenue']);
        }

        return $this->httpCallPing($lead, $delivery, $log, $pingFields);
    }

    protected function callDirectTransfer(Lead $lead, Delivery $delivery, DeliveryLog $log, ?CallSession $session): DeliveryResult
    {
        $destination = $this->callPingService->destinationPhone($delivery);

        if (! filled($destination)) {
            return DeliveryResult::failed('Call destination phone not configured');
        }

        $fields = $lead->allFields();
        $revenue = $this->revenueCalculator->calculate($delivery, $fields);

        if ($session) {
            $this->assignSession($session, $delivery, $revenue, $destination);
        }

        $log->update([
            'post_response' => [
                'handoff' => $session ? 'call_session' : 'queued',
                'destination_phone' => $destination,
                'call_session_uuid' => $session?->uuid,
            ],
            'revenue' => $revenue,
        ]);

        return DeliveryResult::success($revenue);
    }

    protected function callWarmTransfer(Lead $lead, Delivery $delivery, DeliveryLog $log, array $pingFields, ?CallSession $session): DeliveryResult
    {
        $config = $delivery->config ?? [];
        $destination = $this->callPingService->destinationPhone($delivery);

        if ($session && filled($config['ping_url'] ?? null)) {
            $ping = $this->callPingService->ping($session, $delivery);

            if (! $ping['accepted']) {
                return DeliveryResult::skipped('call_ping_rejected');
            }

            $revenue = (float) $ping['revenue'];
            $this->assignSession($session, $delivery, $revenue, $destination);

            $log->update([
                'ping_response' => $ping['response'],
                'post_response' => [
                    'handoff' => 'warm_transfer',
                    'destination_phone' => $destination,
                    'whisper_url' => $config['whisper_url'] ?? null,
                ],
                'revenue' => $revenue,
            ]);

            return DeliveryResult::success($revenue);
        }

        if (! filled($destination)) {
            return DeliveryResult::failed('Warm transfer destination not configured');
        }

        return $this->callDirectTransfer($lead, $delivery, $log, $session);
    }

    protected function httpCallPing(Lead $lead, Delivery $delivery, DeliveryLog $log, array $pingFields): DeliveryResult
    {
        $config = $delivery->config ?? [];
        $pingUrl = $config['ping_url'] ?? null;

        if (! $pingUrl) {
            return DeliveryResult::failed('Missing call ping URL');
        }

        $floor = (float) ($lead->campaign->floor_price ?? 0);
        $pingPayload = $this->payloadBuilder->buildPingPayload($config, $pingFields, $floor, $lead);
        $log->update(['ping_request' => ['url' => $pingUrl, 'body' => $pingPayload]]);

        $response = Http::timeout($config['ping_timeout'] ?? config('performance.ping_timeout_seconds', 2))
            ->post($pingUrl, $pingPayload);
        $body = $response->json() ?? ['raw' => $response->body()];
        $log->update(['ping_response' => $body, 'http_status' => $response->status()]);

        if (! $this->responseMatcher->matchesPingSuccess($config, $body, $floor)) {
            return DeliveryResult::skipped('call_ping_rejected');
        }

        $revenue = $this->revenueCalculator->calculate($delivery, $lead->allFields(), [], $body);
        $destination = $this->callPingService->destinationPhone($delivery);

        $log->update([
            'post_response' => [
                'handoff' => 'http_ping',
                'destination_phone' => $destination,
            ],
            'revenue' => $revenue,
        ]);

        return DeliveryResult::success($revenue, $response->status());
    }

    protected function assignSession(CallSession $session, Delivery $delivery, float $revenue, ?string $destination): void
    {
        $session->update([
            'sold_to_buyer_id' => $delivery->buyer_id,
            'winning_delivery_id' => $delivery->id,
            'revenue' => $revenue,
            'metadata' => array_merge($session->metadata ?? [], array_filter([
                'transfer_number' => $destination,
                'delivery_id' => $delivery->id,
            ])),
        ]);
    }

    protected function resolveSession(Lead $lead): ?CallSession
    {
        $fields = $lead->allFields();
        $uuid = $fields['call_session_uuid']
            ?? $fields['_call_session_uuid']
            ?? data_get($lead->metadata ?? [], 'call_session_uuid');

        if (! filled($uuid)) {
            return null;
        }

        return CallSession::query()
            ->where('uuid', $uuid)
            ->where('account_id', $lead->account_id)
            ->first();
    }
}
