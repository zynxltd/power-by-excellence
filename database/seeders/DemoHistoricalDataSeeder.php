<?php

namespace Database\Seeders;

use App\Enums\DeliveryMethod;
use App\Enums\LeadStatus;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\BuyerTransaction;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\DistributionConfig;
use App\Models\Lead;
use App\Models\LeadFinancial;
use App\Models\LeadEvent;
use App\Models\Source;
use App\Models\Supplier;
use App\Models\User;
use App\Models\AccessLog;
use App\Models\ApiKey;
use App\Models\ApiRequestLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoHistoricalDataSeeder extends Seeder
{
    /** Days of backdated demo leads, logs, and activity per platform. */
    protected int $historyDays = 90;

    public function run(): void
    {
        $this->historyDays = max(1, (int) (env('DEMO_HISTORY_DAYS', $this->historyDays)));

        if ($this->command) {
            $this->command->info("Seeding {$this->historyDays} days of historical demo data for all platforms…");
        }

        foreach (Account::orderBy('id')->get() as $account) {
            $primaryRef = Campaign::where('account_id', $account->id)->orderBy('id')->value('reference');
            if (! $primaryRef) {
                continue;
            }

            if ($this->command) {
                $this->command->line("  → {$account->name}");
            }

            $this->seedExtendedPartners($account);
            $campaign = Campaign::where('account_id', $account->id)->where('reference', $primaryRef)->first();
            if (! $campaign) {
                continue;
            }

            $this->seedTenTierDistribution($campaign, $account);
            $this->seedHistoricalLeads($account, $campaign);
            $this->seedPortalActivity($account);
            $this->seedApiRequestLogs($account);
        }

        $uk = Account::where('slug', 'excellence-uk')->first();
        if ($uk) {
            $this->seedDemoForms($uk);
        }

        if ($this->command) {
            $this->command->info("Historical demo data complete ({$this->historyDays} days per platform).");
        }
    }

    protected function historyDayRange(): array
    {
        return range($this->historyDays - 1, 0);
    }

    protected function seedDemoForms(Account $account): void
    {
        $campaign = Campaign::where('account_id', $account->id)->where('reference', 'auto-insurance-uk')->first();
        if (! $campaign) {
            return;
        }

        \App\Models\HostedForm::updateOrCreate(
            ['slug' => 'auto-insurance-quote-uk'],
            [
                'account_id' => $account->id,
                'campaign_id' => $campaign->id,
                'name' => 'Auto Insurance Quote (Multi-Step)',
                'is_active' => true,
                'config' => [
                    'multi_step' => true,
                    'redirect_url' => url('/help'),
                    'steps' => [
                        [
                            'id' => 'vehicle',
                            'title' => 'Your vehicle',
                            'description' => 'Quick details about the car you want to insure',
                            'fields' => [
                                ['name' => 'vehicle_year', 'label' => 'Vehicle year', 'type' => 'number', 'required' => true, 'options' => []],
                                ['name' => 'vehicle_make', 'label' => 'Make', 'type' => 'text', 'required' => true, 'options' => []],
                                ['name' => 'cover_type', 'label' => 'Cover type', 'type' => 'radio', 'required' => true, 'options' => ['Comprehensive', 'Third party fire & theft', 'Third party only']],
                            ],
                        ],
                        [
                            'id' => 'driver',
                            'title' => 'About you',
                            'description' => 'We need a few details to find your best quote',
                            'fields' => [
                                ['name' => 'firstname', 'label' => 'First name', 'type' => 'text', 'required' => true, 'options' => []],
                                ['name' => 'lastname', 'label' => 'Last name', 'type' => 'text', 'required' => true, 'options' => []],
                                ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true, 'options' => []],
                            ],
                        ],
                        [
                            'id' => 'contact',
                            'title' => 'Contact & postcode',
                            'fields' => [
                                ['name' => 'phone1', 'label' => 'Phone', 'type' => 'tel', 'required' => true, 'options' => []],
                                ['name' => 'zipcode', 'label' => 'Postcode', 'type' => 'postcode', 'required' => true, 'options' => []],
                                ['name' => 'marketing_opt_in', 'label' => 'Marketing', 'type' => 'checkbox', 'required' => false, 'options' => ['I agree to receive quote updates by SMS']],
                            ],
                        ],
                    ],
                ],
            ]
        );
    }

    protected function seedExtendedPartners(Account $account): void
    {
        $isUk = $account->default_country === 'GB';

        $buyerNames = $isUk
            ? ['Hastings Direct', 'LV= Lead Exchange', 'Direct Line Media', 'Churchill Affiliates', 'More Than B2B', 'esure Partner Network', 'NFU Mutual Leads', 'AXA Partner Hub']
            : ['GEICO Lead Partners', 'Allstate Affiliates', 'Liberty Mutual Exchange', 'Farmers Direct Leads', 'Nationwide Media', 'USAA Partner Network', 'Travelers B2B', 'MetLife Lead Hub'];

        $supplierNames = $isUk
            ? ['SearchBright PPC', 'Affiliate Network UK', 'DataSource Partners', 'CompareQuotes Media', 'ClickDrive Affiliates']
            : ['SolarLeads Network', 'HomeQuote Affiliates', 'PPC Masters US', 'LeadGen Partners', 'EnergyCompare Media'];

        foreach ($buyerNames as $i => $name) {
            Buyer::updateOrCreate(
                ['account_id' => $account->id, 'reference' => 'buyer-'.($i + 3)],
                [
                    'name' => $name,
                    'email' => strtolower(str_replace([' ', '='], '', $name)).'@demo.test',
                    'credit_balance' => random_int(100, 500),
                    'caps' => ['daily' => random_int(20, 80)],
                ]
            );
        }

        foreach ($supplierNames as $i => $name) {
            $ref = 'supplier-'.($i + 2);
            $supplier = Supplier::updateOrCreate(
                ['account_id' => $account->id, 'reference' => $ref],
                ['name' => $name]
            );

            \App\Models\Source::updateOrCreate(
                ['supplier_id' => $supplier->id, 'sid' => str_replace('-', '_', $ref)],
                ['name' => $name.' — Primary Source']
            );
        }
    }

    protected function seedTenTierDistribution(Campaign $campaign, Account $account): void
    {
        $buyers = Buyer::where('account_id', $account->id)->orderBy('id')->get();
        $appUrl = rtrim(config('app.url'), '/');
        $tierDeliveries = [];

        $modes = ['parallel_auction', 'parallel_auction', 'parallel_auction', 'waterfall', 'parallel_auction', 'weighted', 'waterfall', 'parallel_auction', 'sequential_ping', 'waterfall'];
        $floors = [18, 17, 16, 15, 14, 13, 12, 11, 10, 8];

        for ($tier = 1; $tier <= 10; $tier++) {
            $buyer = $buyers->get($tier - 1) ?? $buyers->first();
            $mode = $modes[$tier - 1];
            $delivery = Delivery::updateOrCreate(
                ['campaign_id' => $campaign->id, 'tier' => $tier],
                [
                    'buyer_id' => $buyer->id,
                    'name' => "Tier {$tier} — {$buyer->name}",
                    'method' => $tier === 10 ? DeliveryMethod::StoreLead : DeliveryMethod::PingPost,
                    'status' => 'active',
                    'priority' => $tier * 10,
                    'tier' => $tier,
                    'weight' => max(10, 110 - ($tier * 10)),
                    'revenue_type' => $tier === 10 ? 'fixed' : 'dynamic',
                    'revenue_amount' => $floors[$tier - 1],
                    'advanced_distribution_only' => true,
                    'config' => $tier === 10 ? [] : [
                        'ping_url' => "{$appUrl}/api/v1/ping",
                        'post_url' => "{$appUrl}/api/v1/post",
                        'ping_timeout' => 5,
                        'timeout' => 10,
                        'revenue_field' => 'Cost',
                        'bid_hint' => $floors[$tier - 1] + ($tier % 3),
                    ],
                ]
            );
            $tierDeliveries[] = $delivery->id;
        }

        DistributionConfig::updateOrCreate(
            ['campaign_id' => $campaign->id, 'name' => '10-Tier Enterprise Ping Tree'],
            [
                'is_active' => true,
                'config' => [
                    'groups' => collect(range(1, 10))->map(fn ($tier) => [
                        'name' => "Tier {$tier}",
                        'mode' => $modes[$tier - 1],
                        'floor_price' => $floors[$tier - 1],
                        'delivery_ids' => [$tierDeliveries[$tier - 1]],
                    ])->all(),
                ],
            ]
        );

        DistributionConfig::where('campaign_id', $campaign->id)
            ->where('name', 'Hybrid Ping Tree')
            ->update(['is_active' => false]);
    }

    protected function seedHistoricalLeads(Account $account, Campaign $campaign): void
    {
        $suppliers = Supplier::where('account_id', $account->id)->get();
        $buyers = Buyer::where('account_id', $account->id)->get();
        $deliveries = Delivery::where('campaign_id', $campaign->id)->get();
        $otherCampaigns = Campaign::where('account_id', $account->id)->where('id', '!=', $campaign->id)->get();

        $firstNames = ['James', 'Sarah', 'Oliver', 'Emma', 'Noah', 'Ava', 'Leo', 'Mia', 'Arthur', 'Isla', 'George', 'Lily'];
        $lastNames = ['Smith', 'Jones', 'Taylor', 'Brown', 'Wilson', 'Davies', 'Evans', 'Thomas', 'Roberts', 'Walker'];
        $postcodes = $account->default_country === 'US'
            ? ['90210', '73301', '10001', '33101', '60601', '98101']
            : ['SW1A 1AA', 'EC1A 1BB', 'W1A 0AX', 'M1 1AE', 'B1 1AA', 'LS1 1UR', 'EH1 1YZ', 'CF10 1EP'];

        $statusWeights = [
            LeadStatus::Sold->value => 62,
            LeadStatus::Rejected->value => 12,
            LeadStatus::Unsold->value => 18,
            LeadStatus::Quarantined->value => 4,
            LeadStatus::Accepted->value => 4,
        ];

        foreach ($this->historyDayRange() as $day) {
            $date = now()->subDays($day)->startOfDay();
            $dailyCount = random_int(18, 42);

            for ($i = 0; $i < $dailyCount; $i++) {
                $useCampaign = random_int(1, 100) <= 55 ? $campaign : $otherCampaigns->random();
                $status = $this->weightedStatus($statusWeights);
                $buyer = $buyers->random();
                $supplier = $suppliers->random();
                $source = $supplier->sources()->first();
                $receivedAt = $date->copy()->addMinutes(random_int(6, 22 * 60));

                $fields = [
                    'firstname' => $firstNames[array_rand($firstNames)],
                    'lastname' => $lastNames[array_rand($lastNames)],
                    'email' => Str::lower(Str::random(6)).'.'.$day.$i.'@demo-hist.test',
                    'phone1' => '07'.random_int(100000000, 999999999),
                    'zipcode' => $postcodes[array_rand($postcodes)],
                    'vehicle_year' => (string) random_int(2012, 2024),
                ];

                $lead = Lead::create([
                    'account_id' => $account->id,
                    'campaign_id' => $useCampaign->id,
                    'supplier_id' => $supplier->id,
                    'source_id' => $source?->id,
                    'sold_to_buyer_id' => $status === LeadStatus::Sold->value ? $buyer->id : null,
                    'status' => $status,
                    'reject_reason' => $status === LeadStatus::Rejected->value ? collect(['Duplicate email', 'Campaign cap reached', 'Invalid phone', 'Suppression list'])->random() : null,
                    'field_data' => $fields,
                    'sid' => $source?->sid ?? 'google_search',
                    'received_at' => $receivedAt,
                    'distributed_at' => in_array($status, [LeadStatus::Sold->value, LeadStatus::Unsold->value], true) ? $receivedAt->copy()->addSeconds(random_int(1, 45)) : null,
                    'processing_ms' => in_array($status, [LeadStatus::Sold->value, LeadStatus::Unsold->value, LeadStatus::Rejected->value], true)
                        ? random_int(72, 185)
                        : null,
                    'quarantined_until' => $status === LeadStatus::Quarantined->value ? now()->addDays(2) : null,
                    'metadata' => $status === LeadStatus::Quarantined->value ? $this->quarantineMetadata() : null,
                ]);

                if ($status === LeadStatus::Sold->value) {
                    $revenue = round(random_int(1100, 2800) / 100, 2);
                    LeadFinancial::create([
                        'lead_id' => $lead->id,
                        'revenue' => $revenue,
                        'payout' => round($revenue * 0.35, 2),
                        'margin' => round($revenue * 0.65, 2),
                        'currency' => $useCampaign->currency,
                    ]);
                }

                LeadEvent::create([
                    'lead_id' => $lead->id,
                    'event_type' => 'lead.ingested',
                    'level' => 'info',
                    'message' => 'Lead received via API from '.$supplier->name,
                    'created_at' => $receivedAt,
                ]);

                if ($status !== LeadStatus::Pending->value) {
                    LeadEvent::create([
                        'lead_id' => $lead->id,
                        'event_type' => $status === LeadStatus::Sold->value ? 'sold' : 'processed',
                        'level' => 'info',
                        'message' => $status === LeadStatus::Sold->value
                            ? 'Lead sold to '.$buyer->name
                            : 'Lead processed — status: '.$status,
                        'created_at' => $receivedAt->copy()->addSeconds(random_int(2, 30)),
                    ]);
                }

                $this->seedDeliveryLogsForLead($lead, $deliveries, $buyers, $status);
            }
        }
    }

    protected function seedDeliveryLogsForLead(Lead $lead, $deliveries, $buyers, string $status): void
    {
        if ($lead->campaign_id !== $deliveries->first()?->campaign_id) {
            return;
        }

        $tierCount = min(10, random_int(3, 10));

        for ($t = 0; $t < $tierCount; $t++) {
            $delivery = $deliveries->get($t) ?? $deliveries->random();
            $isWinningTier = $status === LeadStatus::Sold->value && $t === $tierCount - 1;
            $isEarlierTier = $t < $tierCount - 1;

            $logStatus = match (true) {
                $isWinningTier => 'success',
                $status === LeadStatus::Rejected->value => random_int(1, 100) <= 1
                    ? 'failed'
                    : (random_int(0, 1) ? 'skipped' : 'outbid'),
                $isEarlierTier => random_int(1, 100) <= 45 ? 'outbid' : 'skipped',
                default => collect(['skipped', 'outbid', 'ping_ok'])->random(),
            };

            $skippedReason = match ($logStatus) {
                'outbid' => 'auction_lost',
                'skipped' => collect(['ping_rejected', 'floor_not_met', 'eligibility_rules', 'insufficient_credit'])->random(),
                'failed' => collect(['post_rejected', 'buyer_timeout'])->random(),
                default => null,
            };

            $revenue = $logStatus === 'success' ? (float) ($lead->financials?->revenue ?? random_int(1200, 2500) / 100) : 0;

            DeliveryLog::create([
                'lead_id' => $lead->id,
                'delivery_id' => $delivery->id,
                'buyer_id' => $delivery->buyer_id,
                'status' => $logStatus,
                'skipped_reason' => $skippedReason,
                'revenue' => $revenue,
                'duration_ms' => random_int(18, 95),
                'http_status' => $logStatus === 'success' ? 200 : ($logStatus === 'failed' ? 422 : null),
                'ping_request' => ['url' => '/api/v1/ping', 'tier' => $t + 1],
                'ping_response' => ['Success' => ! in_array($logStatus, ['failed'], true) || $skippedReason === 'buyer_timeout', 'Cost' => $revenue ?: random_int(800, 2200) / 100],
                'post_request' => in_array($logStatus, ['success', 'failed'], true) ? ['url' => '/api/v1/post'] : null,
                'post_response' => match ($logStatus) {
                    'success' => ['Success' => true, 'Approved' => true],
                    'failed' => ['Success' => false, 'message' => 'Rejected'],
                    default => null,
                },
                'created_at' => $lead->received_at?->copy()->addSeconds($t * 2 + 1),
                'updated_at' => $lead->received_at?->copy()->addSeconds($t * 2 + 2),
            ]);
        }
    }

    protected function seedPortalActivity(Account $account): void
    {
        $buyers = Buyer::where('account_id', $account->id)->get();
        $users = User::where('account_id', $account->id)->get();

        $weeks = (int) ceil($this->historyDays / 7);

        foreach ($buyers as $buyer) {
            $balance = (float) $buyer->credit_balance;
            for ($week = $weeks; $week >= 0; $week--) {
                $topUp = random_int(50, 200);
                $balance += $topUp;
                BuyerTransaction::create([
                    'buyer_id' => $buyer->id,
                    'type' => 'credit',
                    'amount' => $topUp,
                    'balance_after' => $balance,
                    'description' => 'Weekly credit top-up',
                    'created_at' => now()->subWeeks($week)->subDays(random_int(0, 2)),
                ]);

                for ($c = 0; $c < random_int(8, 25); $c++) {
                    $charge = round(random_int(800, 2200) / 100, 2);
                    $balance = max(0, $balance - $charge);
                    BuyerTransaction::create([
                        'buyer_id' => $buyer->id,
                        'type' => 'debit',
                        'amount' => $charge,
                        'balance_after' => $balance,
                        'description' => 'Lead purchase',
                        'created_at' => now()->subWeeks($week)->addDays(random_int(0, 6))->addHours(random_int(8, 18)),
                    ]);
                }
            }
        }

        foreach ($users as $user) {
            foreach ($this->historyDayRange() as $d) {
                if (random_int(1, 100) > 70) {
                    continue;
                }
                AccessLog::create([
                    'account_id' => $account->id,
                    'user_id' => $user->id,
                    'action' => collect(['login', 'login', 'page_view'])->random(),
                    'ip_address' => '86.'.random_int(10, 200).'.'.random_int(1, 254).'.'.random_int(1, 254),
                    'user_agent' => 'Mozilla/5.0 Demo Browser',
                    'path' => collect(['/dashboard', '/deliveries', '/reports', '/buyers', '/campaigns'])->random(),
                    'created_at' => now()->subDays($d)->addHours(random_int(7, 20)),
                ]);
            }
        }
    }

    protected function weightedStatus(array $weights): string
    {
        $total = array_sum($weights);
        $rand = random_int(1, $total);
        $cumulative = 0;
        foreach ($weights as $status => $weight) {
            $cumulative += $weight;
            if ($rand <= $cumulative) {
                return $status;
            }
        }

        return LeadStatus::Sold->value;
    }

  /**
     * @return array<string, mixed>
     */
    protected function quarantineMetadata(): array
    {
        $type = collect(['validation', 'validation', 'out_of_hours', 'unsold', 'hold'])->random();

        return match ($type) {
            'validation' => [
                'quarantine_reason' => 'validation',
                'quarantine_message' => collect([
                    'Email validation failed: undeliverable',
                    'HLR check failed: invalid mobile number',
                    'Invalid email address',
                ])->random(),
                'email_validation' => ['passed' => false, 'status' => 'invalid', 'provider' => 'demo'],
            ],
            'out_of_hours' => [
                'quarantine_reason' => 'out_of_hours',
                'quarantine_message' => 'Out of hours — held for next delivery window',
            ],
            'unsold' => [
                'quarantine_reason' => 'unsold',
                'quarantine_message' => 'Unsold — held for retry',
            ],
            default => [
                'quarantine_reason' => 'hold',
                'quarantine_message' => 'General hold — manual review',
            ],
        };
    }

    protected function seedApiRequestLogs(?Account $account): void
    {
        if (! $account) {
            return;
        }

        $apiKey = ApiKey::where('account_id', $account->id)->first();
        $paths = ['/api/v1/leads', '/api/v1/leads/search', '/api/v1/reports/leads', '/api/v1/quarantine'];
        $errors = [
            'Invalid API key',
            'Insufficient permissions',
            'Required field missing: email',
            'Campaign cap reached',
        ];

        foreach ($this->historyDayRange() as $day) {
            $count = random_int(12, 35);
            for ($i = 0; $i < $count; $i++) {
                $isError = random_int(1, 100) <= 18;
                $status = $isError ? collect([401, 403, 422, 500])->random() : collect([200, 201, 202])->random();

                ApiRequestLog::create([
                    'account_id' => $account->id,
                    'api_key_id' => $apiKey?->id,
                    'method' => collect(['POST', 'GET'])->random(),
                    'path' => $paths[array_rand($paths)],
                    'status_code' => $status,
                    'duration_ms' => random_int(25, $isError ? 3200 : 890),
                    'error_message' => $isError ? $errors[array_rand($errors)] : null,
                    'response_summary' => $isError ? ['error' => $errors[array_rand($errors)]] : ['status' => 'ok'],
                    'ip_address' => '203.0.'.random_int(10, 200).'.'.random_int(1, 254),
                    'created_at' => now()->subDays($day)->addMinutes(random_int(0, 1439)),
                ]);
            }
        }
    }
}
