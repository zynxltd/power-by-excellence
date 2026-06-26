<?php

namespace App\Services\Distribution;

use App\Enums\LeadStatus;
use App\Enums\RoutingMode;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\Lead;
use App\Models\LeadFinancial;
use App\Services\Caps\CapService;
use App\Services\Delivery\DeliveryExecutor;
use App\Services\Logging\PlatformLogger;
use App\Services\Rules\RuleEngine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class DistributionEngine
{
    protected array $roundRobinIndex = [];

    public function __construct(
        protected DeliveryExecutor $executor,
        protected CapService $capService,
        protected RuleEngine $ruleEngine,
    ) {}

    public function distribute(Lead $lead): DistributionResult
    {
        $campaign = $lead->campaign;
        $lead->update(['status' => LeadStatus::Distributing]);

        if ($campaign->use_advanced_distribution) {
            return $this->distributeAdvanced($lead, $campaign);
        }

        return $this->distributeStandard($lead, $campaign);
    }

    protected function distributeStandard(Lead $lead, Campaign $campaign): DistributionResult
    {
        $deliveries = $campaign->deliveries()
            ->where('status', 'active')
            ->where('trigger_type', 'on_lead_arrival')
            ->where('advanced_distribution_only', false)
            ->orderBy('priority')
            ->get();

        return $this->runDeliveries($lead, $campaign, $deliveries);
    }

    protected function distributeAdvanced(Lead $lead, Campaign $campaign): DistributionResult
    {
        $config = $campaign->distributionConfigs()->where('is_active', true)->first();
        $groups = $config?->config['groups'] ?? [];

        if (empty($groups)) {
            return $this->distributeStandard($lead, $campaign);
        }

        $totalRevenue = 0;
        $sells = 0;
        $soldBuyers = [];

        foreach ($groups as $tierIndex => $group) {
            if (! empty($group['hybrid_rule_group_id'])) {
                $rules = \App\Models\HybridRuleGroup::find($group['hybrid_rule_group_id'])?->rules;
                if ($rules && ! $this->ruleEngine->matches($rules, $lead->allFields())) {
                    PlatformLogger::leadEvent($lead, 'distribution.skipped_group', 'Hybrid rule group mismatch', ['group' => $group['name'] ?? '']);

                    continue;
                }
            }

            if (! empty($group['rules'])) {
                $fields = $lead->allFields();
                $failed = $this->ruleEngine->firstFailingCondition($group['rules'], $fields);

                if ($failed !== null) {
                    $tierName = $group['name'] ?? 'Tier '.($tierIndex + 1);
                    $description = $this->ruleEngine->describeCondition($failed, $fields);

                    PlatformLogger::leadEvent(
                        $lead,
                        'distribution.tier_filtered',
                        "Skipped {$tierName}: {$description}",
                        [
                            'tier' => $tierName,
                            'tier_index' => $tierIndex + 1,
                            'failed_condition' => $failed,
                            'filter_summary' => $this->ruleEngine->summarizeRules($group['rules']),
                            'lead_field' => $failed['field'] ?? null,
                            'lead_value' => isset($failed['field']) ? data_get($fields, $failed['field']) : null,
                        ],
                    );

                    continue;
                }
            }

            $deliveryIds = $group['delivery_ids'] ?? [];
            $deliveries = Delivery::whereIn('id', $deliveryIds)->where('status', 'active')->get();
            $mode = RoutingMode::tryFrom($group['mode'] ?? 'waterfall') ?? RoutingMode::Waterfall;

            $result = match ($mode) {
                RoutingMode::ParallelAuction => $this->runParallelAuction($lead, $campaign, $deliveries, $group),
                RoutingMode::SequentialPing => $this->runSequential($lead, $campaign, $deliveries),
                RoutingMode::Weighted => $this->runWeighted($lead, $campaign, $deliveries),
                RoutingMode::RoundRobin => $this->runRoundRobin($lead, $campaign, $deliveries, $group),
                default => $this->runWaterfall($lead, $campaign, $deliveries),
            };

            if ($result->sold) {
                $totalRevenue += $result->revenue;
                $sells++;
                if ($result->buyerId) {
                    $soldBuyers[] = $result->buyerId;
                }

                if ($campaign->sell_mode === 'exclusive' || $sells >= $campaign->max_sells) {
                    return $this->finalizeSold($lead, $campaign, $totalRevenue, $soldBuyers[0] ?? null);
                }
            }
        }

        if ($sells > 0) {
            return $this->finalizeSold($lead, $campaign, $totalRevenue, $soldBuyers[0] ?? null);
        }

        return $this->finalizeUnsold($lead);
    }

    protected function runDeliveries(Lead $lead, Campaign $campaign, Collection $deliveries): DistributionResult
    {
        $totalRevenue = 0;
        $sells = 0;
        $buyerId = null;

        foreach ($deliveries as $delivery) {
            if (! $this->canDeliver($lead, $delivery)) {
                continue;
            }

            $pingFields = $this->pingFields($lead, $campaign);
            $result = $this->executor->execute($lead, $delivery, $pingFields);

            if ($result->success) {
                $revenue = $this->assignRevenue($delivery, $result->revenue, $lead->allFields());
                app(\App\Services\Buyers\BuyerEligibilityService::class)
                    ->recordSuccessfulDelivery($lead, $delivery, $revenue);

                $totalRevenue += $revenue;
                $sells++;
                $buyerId = $delivery->buyer_id;

                PlatformLogger::leadEvent($lead, 'delivery.success', "Sold via {$delivery->name}", [
                    'delivery_id' => $delivery->id,
                    'revenue' => $revenue,
                ]);

                app(\App\Services\Postbacks\PostbackDispatcher::class)->dispatch($lead->fresh(), 'delivery.success');

                if ($campaign->sell_mode === 'exclusive') {
                    return $this->finalizeSold($lead, $campaign, $totalRevenue, $buyerId);
                }

                if ($sells >= $campaign->max_sells) {
                    break;
                }
            }
        }

        if ($sells > 0) {
            return $this->finalizeSold($lead, $campaign, $totalRevenue, $buyerId);
        }

        return $this->finalizeUnsold($lead);
    }

    protected function runWaterfall(Lead $lead, Campaign $campaign, Collection $deliveries): DistributionResult
    {
        $sorted = $deliveries->sortBy('priority');

        return $this->runDeliveries($lead, $campaign, $sorted);
    }

    protected function runSequential(Lead $lead, Campaign $campaign, Collection $deliveries): DistributionResult
    {
        return $this->runWaterfall($lead, $campaign, $deliveries);
    }

    protected function runParallelAuction(Lead $lead, Campaign $campaign, Collection $deliveries, array $group): DistributionResult
    {
        $floor = (float) ($group['floor_price'] ?? $campaign->floor_price);
        $pingFields = $this->pingFields($lead, $campaign);
        $candidates = [];
        $poolRequests = [];
        $poolStartedAt = microtime(true);

        foreach ($deliveries as $delivery) {
            if (! $this->canDeliver($lead, $delivery)) {
                continue;
            }

            if ($delivery->method === \App\Enums\DeliveryMethod::PingPost) {
                $prepared = $this->executor->preparePingRequest($lead, $delivery, $pingFields, $floor);
                if ($prepared) {
                    $poolRequests[] = $prepared;
                }

                continue;
            }

            $ping = $this->executor->pingOnly($lead, $delivery, $pingFields, $floor);

            if ($ping->skipped) {
                continue;
            }

            if ($ping->success && $ping->revenue >= $floor) {
                $candidates[] = [
                    'delivery' => $delivery,
                    'revenue' => $ping->revenue,
                    'ping_body' => $ping->pingBody,
                    'log' => $ping->log,
                ];
            } elseif ($ping->log) {
                $ping->log->update(['status' => 'outbid', 'skipped_reason' => 'auction_lost']);
            }
        }

        if (! empty($poolRequests)) {
            $timeout = (int) config('performance.ping_timeout_seconds', 2);
            $responses = Http::pool(fn ($pool) => collect($poolRequests)->mapWithKeys(
                fn (array $prepared) => [
                    $prepared['delivery']->id => $pool->as((string) $prepared['delivery']->id)
                        ->timeout($timeout)
                        ->post($prepared['pingUrl'], $prepared['pingPayload']),
                ]
            )->all());

            foreach ($poolRequests as $prepared) {
                $delivery = $prepared['delivery'];
                $ping = $this->executor->finalizePingFromResponse(
                    $prepared,
                    $responses[$delivery->id] ?? null,
                    $poolStartedAt
                );

                if ($ping->skipped) {
                    continue;
                }

                if ($ping->success && $ping->revenue >= $floor) {
                    $candidates[] = [
                        'delivery' => $delivery,
                        'revenue' => $ping->revenue,
                        'ping_body' => $ping->pingBody,
                        'log' => $ping->log,
                    ];
                } elseif ($ping->log) {
                    $ping->log->update(['status' => 'outbid', 'skipped_reason' => 'auction_lost']);
                }
            }
        }

        if (empty($candidates)) {
            return new DistributionResult(false);
        }

        usort($candidates, fn ($a, $b) => $b['revenue'] <=> $a['revenue']);
        $winner = $candidates[0];

        foreach (array_slice($candidates, 1) as $loser) {
            $loser['log']?->update(['status' => 'outbid', 'skipped_reason' => 'auction_lost']);
        }

        PlatformLogger::leadEvent($lead, 'auction.won', "Won by {$winner['delivery']->name}", [
            'delivery_id' => $winner['delivery']->id,
            'bid' => $winner['revenue'],
            'floor' => $floor,
            'competitors' => count($candidates) - 1,
        ]);

        $result = $this->executor->completePingPost(
            $lead,
            $winner['delivery'],
            $lead->allFields(),
            $pingFields,
            $winner['ping_body'],
            $winner['log']
        );

        if (! $result->success) {
            return new DistributionResult(false);
        }

        $revenue = $this->assignRevenue($winner['delivery'], $result->revenue, $lead->allFields());
        app(\App\Services\Buyers\BuyerEligibilityService::class)
            ->recordSuccessfulDelivery($lead, $winner['delivery'], $revenue);

        LeadFinancial::updateOrCreate(
            ['lead_id' => $lead->id],
            ['revenue' => $revenue, 'margin' => $revenue, 'currency' => $campaign->currency]
        );

        return new DistributionResult(true, $revenue, $winner['delivery']->buyer_id);
    }

    protected function runWeighted(Lead $lead, Campaign $campaign, Collection $deliveries): DistributionResult
    {
        $eligible = $deliveries->filter(fn ($d) => $this->canDeliver($lead, $d));
        if ($eligible->isEmpty()) {
            return new DistributionResult(false);
        }

        $totalWeight = $eligible->sum('weight');
        $rand = random_int(1, max(1, $totalWeight));
        $cumulative = 0;
        $selected = $eligible->first();

        foreach ($eligible as $delivery) {
            $cumulative += $delivery->weight;
            if ($rand <= $cumulative) {
                $selected = $delivery;
                break;
            }
        }

        return $this->runDeliveries($lead, $campaign, collect([$selected]));
    }

    protected function runRoundRobin(Lead $lead, Campaign $campaign, Collection $deliveries, array $group): DistributionResult
    {
        $key = 'rr_'.$campaign->id.'_'.($group['id'] ?? 'default');
        $eligible = $deliveries->filter(fn ($d) => $this->canDeliver($lead, $d))->values();
        if ($eligible->isEmpty()) {
            return new DistributionResult(false);
        }

        $index = $this->roundRobinIndex[$key] ?? 0;
        $selected = $eligible[$index % $eligible->count()];
        $this->roundRobinIndex[$key] = $index + 1;

        return $this->runDeliveries($lead, $campaign, collect([$selected]));
    }

    protected function canDeliver(Lead $lead, Delivery $delivery): bool
    {
        return app(\App\Services\Buyers\BuyerEligibilityService::class)->canDeliver($lead, $delivery);
    }

    protected function pingFields(Lead $lead, Campaign $campaign): array
    {
        $all = $lead->allFields();
        $pingFieldNames = $campaign->fields()->where('ping_field', true)->pluck('name')->all();

        if (empty($pingFieldNames)) {
            $sensitive = ['firstname', 'lastname', 'email', 'phone1', 'phone2', 'phone3', 'address'];
            return array_diff_key($all, array_flip($sensitive));
        }

        return array_intersect_key($all, array_flip($pingFieldNames));
    }

    protected function assignRevenue(Delivery $delivery, float $dynamicRevenue, array $leadFields = []): float
    {
        return app(\App\Services\Billing\RevenueCalculator::class)->calculate(
            $delivery,
            $leadFields,
            [],
            [],
            $dynamicRevenue > 0 ? $dynamicRevenue : null,
        );
    }

    protected function finalizeSold(Lead $lead, Campaign $campaign, float $revenue, ?int $buyerId): DistributionResult
    {
        $payout = (float) $campaign->payout_amount;

        LeadFinancial::updateOrCreate(
            ['lead_id' => $lead->id],
            [
                'revenue' => $revenue,
                'payout' => $payout,
                'margin' => $revenue - $payout,
                'currency' => $campaign->currency,
            ]
        );

        $lead->update([
            'status' => LeadStatus::Sold,
            'sold_to_buyer_id' => $buyerId,
            'distributed_at' => now(),
        ]);

        app(\App\Services\Leads\LeadRedirectService::class)->offerRedirect($lead->fresh());

        if ($buyerId) {
            $buyer = \App\Models\Buyer::find($buyerId);
            if ($buyer) {
                $charged = app(\App\Services\Billing\BuyerBillingService::class)->charge($buyer, $revenue, $lead);
                if (! $charged) {
                    PlatformLogger::leadEvent($lead, 'billing.charge_failed', 'Buyer credit debit failed after sale', [
                        'buyer_id' => $buyer->id,
                        'revenue' => $revenue,
                    ]);
                } else {
                    app(\App\Services\Buyers\BuyerNotificationService::class)->notifyLeadPurchase($buyer, $lead, $revenue);
                }
            }
        }

        PlatformLogger::leadEvent($lead, 'lead.sold', 'Lead sold', ['revenue' => $revenue]);

        app(\App\Services\Automation\AutoResponderService::class)->dispatchForLead($lead, 'on_lead_sold');
        app(\App\Services\Automation\AutomationSequenceService::class)->dispatchForLead($lead, 'on_lead_sold');

        app(WebhookDispatcher::class)->dispatch($lead->account()->first(), 'lead.sold', $lead);
        app(\App\Services\Postbacks\PostbackDispatcher::class)->dispatch($lead->fresh(), 'lead.sold');

        return new DistributionResult(true, $revenue, $buyerId);
    }

    protected function finalizeUnsold(Lead $lead): DistributionResult
    {
        $campaign = $lead->campaign;
        if ($campaign && ($campaign->validation_config['quarantine_unsold'] ?? true)) {
            app(\App\Services\Leads\QuarantineService::class)
                ->quarantineUnsold($lead, $campaign, 'Unsold after ping tree — held for retry');

            return new DistributionResult(false);
        }

        if ($lead->quarantined_until && $lead->quarantined_until->isFuture()) {
            $lead->update(['status' => LeadStatus::Quarantined]);

            return new DistributionResult(false);
        }

        $lead->update(['status' => LeadStatus::Unsold]);
        PlatformLogger::leadEvent($lead, 'lead.unsold', 'Lead unsold', [], 'warning');
        app(\App\Services\Automation\AutomationSequenceService::class)->dispatchForLead($lead, 'on_lead_unsold');
        app(WebhookDispatcher::class)->dispatch($lead->account()->first(), 'lead.unsold', $lead);
        app(\App\Services\Postbacks\PostbackDispatcher::class)->dispatch($lead->fresh(), 'lead.unsold');

        return new DistributionResult(false);
    }
}
