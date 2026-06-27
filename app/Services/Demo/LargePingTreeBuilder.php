<?php

namespace App\Services\Demo;

use App\Enums\DeliveryMethod;
use App\Enums\RoutingMode;
use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DistributionConfig;
use App\Models\User;

class LargePingTreeBuilder
{
    /**
     * @return array{tiers: int, deliveries: int, buyers: int, distribution_id: int}
     */
    public function build(
        Campaign $campaign,
        Account $account,
        DistributionConfig $distribution,
        int $tierCount = 35,
    ): array {
        $buyers = $this->ensureBuyers($account, $tierCount + 8);
        $appUrl = rtrim(config('app.url'), '/');
        $groups = [];
        $deliveryCount = 0;
        $buyerIndex = 0;

        $modeCycle = [
            RoutingMode::ParallelAuction->value,
            RoutingMode::ParallelAuction->value,
            RoutingMode::Waterfall->value,
            RoutingMode::Weighted->value,
            RoutingMode::SequentialPing->value,
            RoutingMode::RoundRobin->value,
            RoutingMode::ParallelAuction->value,
            RoutingMode::Waterfall->value,
        ];

        for ($tier = 1; $tier <= $tierCount; $tier++) {
            $floor = max(8.0, 52.0 - ($tier * 1.15));
            $mode = $modeCycle[($tier - 1) % count($modeCycle)];
            $tierDeliveryIds = [];
            $slots = ($tier % 5 === 0 && $tier < $tierCount) ? 2 : 1;

            if ($tier === $tierCount) {
                $mode = RoutingMode::Waterfall->value;
                $slots = 1;
            }

            for ($slot = 0; $slot < $slots; $slot++) {
                $buyer = $buyers[$buyerIndex % $buyers->count()];
                $buyerIndex++;
                $method = $this->methodForTier($tier, $tierCount, $slot);
                $name = $this->deliveryName($tier, $slot, $buyer->name, $method);

                $delivery = Delivery::updateOrCreate(
                    ['campaign_id' => $campaign->id, 'name' => $name],
                    $this->deliveryPayload(
                        $campaign,
                        $buyer,
                        $tier,
                        $floor,
                        $method,
                        $appUrl,
                        $slot,
                    ),
                );

                $tierDeliveryIds[] = $delivery->id;
                $deliveryCount++;
            }

            $groups[] = [
                'name' => $this->tierName($tier, $mode, $floor),
                'mode' => $mode,
                'floor_price' => round($floor, 2),
                'delivery_ids' => $tierDeliveryIds,
                'redirect_url' => $tier === 1 ? 'https://example.com/thank-you-premium' : null,
            ];
        }

        $distribution->update([
            'name' => "{$tierCount}-Tier Enterprise Ping Tree",
            'is_active' => true,
            'is_locked' => false,
            'config' => ['groups' => $groups],
        ]);

        DistributionConfig::where('campaign_id', $campaign->id)
            ->where('id', '!=', $distribution->id)
            ->update(['is_active' => false]);

        $this->ensurePortalUsers($account);

        return [
            'tiers' => $tierCount,
            'deliveries' => $deliveryCount,
            'buyers' => $buyers->count(),
            'distribution_id' => $distribution->id,
        ];
    }

    /**
     * @return \Illuminate\Support\Collection<int, Buyer>
     */
    protected function ensureBuyers(Account $account, int $minimum): \Illuminate\Support\Collection
    {
        $existing = Buyer::where('account_id', $account->id)->orderBy('id')->get();

        $needed = max(0, $minimum - $existing->count());
        $names = $this->buyerNamePool($account);

        for ($i = 0; $i < $needed; $i++) {
            $n = $existing->count() + $i + 1;
            $label = $names[($n - 1) % count($names)].($n > count($names) ? " #{$n}" : '');

            Buyer::updateOrCreate(
                ['account_id' => $account->id, 'reference' => 'buyer-'.str_pad((string) $n, 3, '0', STR_PAD_LEFT)],
                [
                    'name' => $label,
                    'email' => 'buyer'.str_pad((string) $n, 3, '0', STR_PAD_LEFT).'@'.$account->slug.'.test',
                    'status' => 'active',
                    'credit_balance' => 200 + ($n * 25),
                    'currency' => $account->default_currency,
                    'caps' => [
                        'daily' => 15 + ($n % 40),
                        'daily_spend_cap' => 500 + ($n * 20),
                    ],
                ],
            );
        }

        return Buyer::where('account_id', $account->id)->orderBy('id')->get();
    }

    /**
     * Demo buyers use their buyer email as portal login (password: password).
     */
    public function ensurePortalUsers(Account $account): void
    {
        Buyer::query()
            ->where('account_id', $account->id)
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->orderBy('id')
            ->each(function (Buyer $buyer): void {
                $existingForBuyer = User::query()
                    ->where('buyer_id', $buyer->id)
                    ->where('role', UserRole::BuyerPortal)
                    ->first();

                if ($existingForBuyer) {
                    return;
                }

                User::updateOrCreate(
                    [
                        'account_id' => $buyer->account_id,
                        'email' => $buyer->email,
                    ],
                    [
                        'buyer_id' => $buyer->id,
                        'name' => $buyer->name.' Portal',
                        'role' => UserRole::BuyerPortal,
                        'password' => 'password',
                    ],
                );
            });
    }

    /**
     * @return list<string>
     */
    protected function buyerNamePool(Account $account): array
    {
        if ($account->default_country === 'NL' || str_contains($account->slug, 'emea')) {
            return [
                'ING Lead Desk', 'ABN AMRO Media', 'Rabobank Exchange', 'BNP Paribas Leads', 'Deutsche Finance EU',
                'Commerzbank Partners', 'Santander Iberia', 'BBVA Lead Network', 'Intesa Sanpaolo B2B', 'UniCredit Direct',
                'Nordea Partner Hub', 'SEB Lead Exchange', 'DNB Affiliate Media', 'KBC Brussels Buyers', 'Erste Group Leads',
                'Raiffeisen EU Exchange', 'Crédit Agricole Media', 'Société Générale Leads', 'BNL Roma Partners', 'CaixaBank Direct',
                'Banco Sabadell Leads', 'OTP Bank Exchange', 'PKO BP Media', 'ČSOB Prague Buyers', 'OTP Hungary Hub',
                'Alpha Bank Leads', 'Piraeus Exchange', 'Bank of Ireland B2B', 'AIB Lead Partners', 'Permanent TSB Media',
                'Swedbank Exchange', 'Handelsbanken Leads', 'OP Financial Partners', 'LHV Tallinn Buyers', 'Luminor Baltic Hub',
                'Eurobank Leads', 'Millennium BCP Media', 'Belfius Exchange', 'Argenta Lead Desk', 'AXA Benelux Partners',
            ];
        }

        return [
            'Premier Buyer One', 'Acme Lead Exchange', 'Summit Finance', 'Northstar Media', 'Velocity Partners',
            'Apex Direct Leads', 'Horizon Buyers', 'Pinnacle Exchange', 'Catalyst Media', 'Meridian Leads',
            'Atlas Partner Network', 'Summit Direct B2B', 'Nova Lead Hub', 'Frontier Exchange', 'Sterling Buyers',
            'Quantum Media', 'Bridge Finance Leads', 'Crown Direct', 'Evergreen Exchange', 'Pulse Partner Hub',
            'Vertex Leads', 'Nimbus Media', 'Orion Exchange', 'Zenith Buyers', 'Aurora Lead Desk',
            'Phoenix Partners', 'Titan Direct', 'Vanguard Exchange', 'Echo Media Leads', 'Prism B2B Hub',
            'Fusion Buyers', 'Helix Exchange', 'Momentum Leads', 'Cascade Media', 'Beacon Partner Network',
            'Summit EU Exchange', 'Clearwater Leads', 'Ironwood Buyers', 'Silverline Media', 'BluePeak Exchange',
        ];
    }

    protected function methodForTier(int $tier, int $tierCount, int $slot): DeliveryMethod
    {
        if ($tier === $tierCount) {
            return DeliveryMethod::StoreLead;
        }

        if ($tier === $tierCount - 1) {
            return DeliveryMethod::Email;
        }

        if ($tier % 11 === 0) {
            return DeliveryMethod::DirectPost;
        }

        if ($tier % 17 === 0) {
            return DeliveryMethod::EmailPingPost;
        }

        return $slot === 1 ? DeliveryMethod::DirectPost : DeliveryMethod::PingPost;
    }

    protected function deliveryName(int $tier, int $slot, string $buyerName, DeliveryMethod $method): string
    {
        $suffix = $slot > 0 ? ' (alt)' : '';
        $methodLabel = match ($method) {
            DeliveryMethod::PingPost => 'Ping-Post',
            DeliveryMethod::DirectPost => 'Direct API',
            DeliveryMethod::StoreLead => 'Store',
            DeliveryMethod::Email => 'Email',
            DeliveryMethod::EmailPingPost => 'Email Ping-Post',
            default => $method->value,
        };

        return "T{$tier} - {$buyerName} - {$methodLabel}{$suffix}";
    }

    protected function tierName(int $tier, string $mode, float $floor): string
    {
        $modeLabel = str_replace('_', ' ', ucwords($mode, '_'));

        return "Tier {$tier} - {$modeLabel} (floor €".number_format($floor, 2).')';
    }

    /**
     * @return array<string, mixed>
     */
    protected function deliveryPayload(
        Campaign $campaign,
        Buyer $buyer,
        int $tier,
        float $floor,
        DeliveryMethod $method,
        string $appUrl,
        int $slot,
    ): array {
        $revenue = round($floor + ($slot * 1.5) + (($tier % 7) * 0.5), 2);
        $priority = $tier * 10 + $slot;
        $weight = max(5, 120 - ($tier * 2) - ($slot * 5));

        $base = [
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'method' => $method,
            'status' => $method === DeliveryMethod::Email ? 'inactive' : 'active',
            'priority' => $priority,
            'weight' => $weight,
            'tier' => $tier,
            'advanced_distribution_only' => true,
            'caps' => ['daily' => 10 + ($tier % 25), 'hourly' => 2 + ($tier % 5)],
        ];

        return match ($method) {
            DeliveryMethod::StoreLead => array_merge($base, [
                'revenue_type' => 'fixed',
                'revenue_amount' => max(8, $floor - 2),
                'config' => ['redirect_url' => 'https://example.com/store-thanks'],
            ]),
            DeliveryMethod::Email => array_merge($base, [
                'revenue_type' => 'fixed',
                'revenue_amount' => $revenue,
                'config' => [
                    'to' => $buyer->email ?? 'alerts@demo.test',
                    'subject' => "Tier {$tier} lead: [firstname] [lastname]",
                    'body' => "Email: [email]\nPhone: [phone1]\nPostcode: [zipcode]",
                ],
            ]),
            DeliveryMethod::DirectPost => array_merge($base, [
                'revenue_type' => $tier % 3 === 0 ? 'rule_based' : 'fixed',
                'revenue_amount' => $revenue,
                'revenue_rules' => $tier % 3 === 0
                    ? [['field' => 'zipcode', 'value' => '10', 'amount' => $revenue + 4]]
                    : null,
                'config' => [
                    'url' => "{$appUrl}/api/v1/post",
                    'http_method' => 'POST',
                    'timeout' => 6 + ($tier % 4),
                ],
            ]),
            DeliveryMethod::EmailPingPost => array_merge($base, [
                'revenue_type' => 'dynamic',
                'revenue_amount' => $floor,
                'config' => [
                    'ping_url' => "{$appUrl}/api/v1/ping",
                    'post_url' => "{$appUrl}/api/v1/post",
                    'ping_timeout' => 4,
                    'timeout' => 9,
                    'revenue_field' => 'Cost',
                    'bid_hint' => $revenue + 2,
                    'notify_email' => $buyer->email,
                ],
            ]),
            default => array_merge($base, [
                'revenue_type' => 'dynamic',
                'revenue_amount' => $floor,
                'config' => [
                    'ping_url' => "{$appUrl}/api/v1/ping",
                    'post_url' => "{$appUrl}/api/v1/post",
                    'ping_timeout' => 3 + ($tier % 3),
                    'timeout' => 8 + ($tier % 5),
                    'revenue_field' => 'Cost',
                    'bid_hint' => $revenue + ($tier % 4),
                ],
            ]),
        };
    }
}
