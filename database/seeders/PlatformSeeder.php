<?php

namespace Database\Seeders;

use App\Enums\DeliveryMethod;
use App\Enums\LeadStatus;
use App\Enums\UserRole;
use App\Jobs\ProcessLeadJob;
use App\Models\AccessLog;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\CampaignField;
use App\Models\Delivery;
use App\Models\DistributionConfig;
use App\Models\Lead;
use App\Models\LeadFinancial;
use App\Models\LeadEvent;
use App\Models\Source;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Api\ApiKeyService;
use App\Services\Billing\BuyerBillingService;
use App\Services\Billing\FraudProtectionService;
use App\Support\VerticalCatalog;
use Illuminate\Database\Seeder;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'admin@powerbyexcellence.test',
            'password' => 'password',
            'role' => UserRole::SuperAdmin,
        ]);

        foreach ($this->platforms() as $platform) {
            $this->seedPlatform($platform);
        }

        $this->command->info('Seeded platforms. Login: admin@powerbyexcellence.test / password');
    }

    protected function seedPlatform(array $platform): void
    {
        $account = Account::create([
            'name' => $platform['name'],
            'slug' => $platform['slug'],
            'domain' => $platform['domain'] ?? ($platform['slug'].'.'.config('tenancy.base_domain', 'powerbyexcellence.test')),
            'timezone' => $platform['timezone'],
            'default_currency' => $platform['default_currency'],
            'default_country' => $platform['default_country'],
            'settings' => app(FraudProtectionService::class)->provisionSettingsForPlan([
                'require_buyer_prepay' => false,
                'validation_integration' => [
                    'enabled' => true,
                    'email_validation' => true,
                    'hlr_validation' => true,
                    'quarantine_on_fail' => true,
                ],
            ], $platform['subscription_plan'] ?? 'growth'),
        ]);

        $user = User::factory()->create([
            'account_id' => $account->id,
            'name' => $platform['admin_name'] ?? 'Platform Administrator',
            'email' => $platform['admin_email'],
            'password' => 'password',
            'role' => UserRole::AccountAdmin,
        ]);

        $buyer = Buyer::create([
            'account_id' => $account->id,
            'reference' => 'buyer-primary',
            'name' => $platform['buyers'][0] ?? 'Primary Buyer',
            'email' => "buyer@{$platform['slug']}.test",
            'credit_balance' => 0,
            'caps' => ['daily' => 100],
        ]);

        $buyerSecondary = Buyer::create([
            'account_id' => $account->id,
            'reference' => 'buyer-secondary',
            'name' => $platform['buyers'][1] ?? 'Secondary Buyer',
            'email' => "buyer2@{$platform['slug']}.test",
            'credit_balance' => 0,
            'caps' => ['daily' => 50],
        ]);

        if (! empty($platform['buyers'][2])) {
            Buyer::create([
                'account_id' => $account->id,
                'reference' => 'buyer-tertiary',
                'name' => $platform['buyers'][2],
                'email' => "buyer3@{$platform['slug']}.test",
                'credit_balance' => 0,
                'caps' => ['daily' => 40],
            ]);
        }

        app(BuyerBillingService::class)->credit($buyer, 500, 'Demo seed top-up');
        app(BuyerBillingService::class)->credit($buyerSecondary, 250, 'Demo seed top-up');

        $supplier = Supplier::create([
            'account_id' => $account->id,
            'reference' => 'supplier-main',
            'name' => $platform['suppliers'][0] ?? 'Main Supplier',
            'affiliate_settings' => [
                'rev_share_percent' => 35,
                'default_postback_url' => url('/api/mock/postback'),
                'enable_sub_suppliers' => true,
                'tracking_params' => ['sid', 'ssid', 'subid'],
            ],
        ]);

        if (! empty($platform['suppliers'][1])) {
            Supplier::create([
                'account_id' => $account->id,
                'reference' => 'supplier-secondary',
                'name' => $platform['suppliers'][1],
                'affiliate_settings' => [
                    'rev_share_percent' => 30,
                    'enable_sub_suppliers' => true,
                ],
            ]);
        }

        $source = Source::create([
            'supplier_id' => $supplier->id,
            'sid' => 'google_search',
            'name' => 'Google Search',
        ]);

        \App\Models\SubSupplier::create([
            'source_id' => $source->id,
            'ssid' => 'sub_google_1',
            'name' => 'Google Sub-Affiliate 1',
        ]);
        \App\Models\SubSupplier::create([
            'source_id' => $source->id,
            'ssid' => 'sub_google_2',
            'name' => 'Google Sub-Affiliate 2',
        ]);

        $firstCampaign = null;
        foreach ($platform['campaigns'] as $campaignDef) {
            $campaign = $this->seedCampaign($account, $platform, $campaignDef, $buyer, $buyerSecondary, $supplier, $source);
            $firstCampaign ??= $campaign;

            if (in_array($campaign->reference, ['auto-insurance-uk', 'auto-insurance-us'], true)) {
                $this->seedDemoLeads($account, $campaign, $supplier, $source, $buyer);
            }
        }

        AccessLog::create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'action' => 'login',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Demo Seeder',
            'path' => 'login',
            'created_at' => now()->subDays(2),
        ]);

        AccessLog::create([
            'account_id' => $account->id,
            'user_id' => $user->id,
            'action' => 'login',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Demo Seeder',
            'path' => 'login',
            'created_at' => now()->subHours(3),
        ]);

        $apiKeyService = app(ApiKeyService::class);
        $adminKey = $apiKeyService->create([
            'account_id' => $account->id,
            'name' => 'Admin API Key',
            'type' => 'administrator',
            'permissions' => ['*'],
        ]);

        $supplierKey = $apiKeyService->create([
            'account_id' => $account->id,
            'supplier_id' => $supplier->id,
            'name' => 'Supplier API Key',
            'type' => 'supplier',
            'permissions' => ['leads.create', 'leads.read'],
        ]);

        $campaignRefs = collect($platform['campaigns'])->pluck('reference')->join(', ');

        $this->command->info("Platform: {$account->name}");
        $this->command->info("  Admin: {$user->email} / password @ {$account->domain}");
        $this->command->info("  Admin API: {$adminKey['token']}");
        $this->command->info("  Supplier API (SID: {$source->sid}): {$supplierKey['token']}");
        $this->command->info("  Campaign refs: {$campaignRefs}");

        User::factory()->create([
            'account_id' => $account->id,
            'buyer_id' => $buyer->id,
            'name' => 'Buyer Portal User',
            'email' => "buyer-portal@{$platform['slug']}.test",
            'password' => 'password',
            'role' => UserRole::BuyerPortal,
        ]);

        User::factory()->create([
            'account_id' => $account->id,
            'supplier_id' => $supplier->id,
            'name' => 'Supplier Portal User',
            'email' => "supplier-portal@{$platform['slug']}.test",
            'password' => 'password',
            'role' => UserRole::SupplierPortal,
        ]);

        $this->command->info("  Buyer portal: buyer-portal@{$platform['slug']}.test / password");
        $this->command->info("  Supplier portal: supplier-portal@{$platform['slug']}.test / password");
    }

    protected function seedCampaign(
        Account $account,
        array $platform,
        array $campaignDef,
        Buyer $buyer,
        Buyer $buyerSecondary,
        Supplier $supplier,
        Source $source,
    ): Campaign {
        $vertical = VerticalCatalog::all()[$campaignDef['vertical']] ?? [];
        $floor = $campaignDef['floor_price'] ?? $vertical['default_floor'] ?? 10;
        $payout = $campaignDef['payout_amount'] ?? $vertical['default_payout'] ?? 5;

        $campaign = Campaign::create([
            'account_id' => $account->id,
            'name' => $campaignDef['name'],
            'reference' => $campaignDef['reference'],
            'country' => $platform['default_country'],
            'currency' => $platform['default_currency'],
            'vertical_id' => $campaignDef['vertical'],
            'payout_amount' => $payout,
            'floor_price' => $floor,
            'bidding_mode' => 'real_time_auction',
            'use_advanced_distribution' => true,
            'dedupe_config' => ['fields' => ['email', 'phone1'], 'reject_days' => 30],
        ]);

        foreach (VerticalCatalog::fieldsFor($campaignDef['vertical']) as $i => $field) {
            CampaignField::create(array_merge($field, ['campaign_id' => $campaign->id, 'sort_order' => $i]));
        }

        $appUrl = rtrim(config('app.url'), '/');

        $storeDelivery = Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => 'Store Lead (Fallback)',
            'method' => DeliveryMethod::StoreLead,
            'status' => 'active',
            'priority' => 10,
            'tier' => 2,
            'revenue_type' => 'fixed',
            'revenue_amount' => $floor + 3,
        ]);

        $pingPrimary = Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => 'Real-Time Auction — Primary',
            'method' => DeliveryMethod::PingPost,
            'status' => 'active',
            'priority' => 20,
            'weight' => 50,
            'tier' => 1,
            'revenue_type' => 'dynamic',
            'revenue_amount' => $floor,
            'advanced_distribution_only' => true,
            'config' => [
                'ping_url' => "{$appUrl}/api/v1/ping",
                'post_url' => "{$appUrl}/api/v1/post",
                'ping_timeout' => 5,
                'timeout' => 10,
                'revenue_field' => 'Cost',
                'bid_hint' => $floor + 4,
            ],
        ]);

        $pingSecondary = Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyerSecondary->id,
            'name' => 'Real-Time Auction — Secondary',
            'method' => DeliveryMethod::PingPost,
            'status' => 'active',
            'priority' => 21,
            'weight' => 50,
            'tier' => 1,
            'revenue_type' => 'dynamic',
            'revenue_amount' => $floor,
            'advanced_distribution_only' => true,
            'config' => [
                'ping_url' => "{$appUrl}/api/v1/ping",
                'post_url' => "{$appUrl}/api/v1/post",
                'ping_timeout' => 5,
                'timeout' => 10,
                'revenue_field' => 'Cost',
                'bid_hint' => $floor + 8,
            ],
        ]);

        Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyerSecondary->id,
            'name' => 'Direct API — Waterfall',
            'method' => DeliveryMethod::DirectPost,
            'status' => 'active',
            'priority' => 30,
            'tier' => 2,
            'revenue_type' => 'rule_based',
            'revenue_amount' => $floor,
            'revenue_rules' => [
                ['field' => 'zipcode', 'value' => 'SW', 'amount' => $floor + 10],
            ],
            'advanced_distribution_only' => true,
            'config' => [
                'url' => "{$appUrl}/api/v1/post",
                'http_method' => 'POST',
                'timeout' => 8,
            ],
        ]);

        Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => 'Email Alert',
            'method' => DeliveryMethod::Email,
            'status' => 'inactive',
            'priority' => 99,
            'revenue_type' => 'fixed',
            'revenue_amount' => $floor,
            'config' => [
                'to' => "buyer@{$platform['slug']}.test",
                'subject' => "New {$campaignDef['name']} Lead: [firstname] [lastname]",
                'body' => "Email: [email]\nPhone: [phone1]\nPostcode: [zipcode]",
            ],
        ]);

        DistributionConfig::create([
            'campaign_id' => $campaign->id,
            'name' => 'Hybrid Ping Tree',
            'is_active' => true,
            'config' => [
                'groups' => [
                    [
                        'name' => 'Tier 1 — Real-Time Auction',
                        'mode' => 'parallel_auction',
                        'floor_price' => $floor,
                        'delivery_ids' => [$pingPrimary->id, $pingSecondary->id],
                    ],
                    [
                        'name' => 'Tier 2 — Waterfall Fallback',
                        'mode' => 'waterfall',
                        'delivery_ids' => [$storeDelivery->id],
                    ],
                ],
            ],
        ]);

        return $campaign;
    }

    protected function platforms(): array
    {
        return config('tenant_platforms', []);
    }

    protected function seedDemoLeads(Account $account, Campaign $campaign, Supplier $supplier, Source $source, Buyer $buyer): void
    {
        $samples = [
            ['Jane', 'Cooper', 'jane.cooper@demo.test', '07700900001', 'SW1A 1AA', ''],
            ['Mark', 'Stevens', 'mark.stevens@demo.test', '07700900002', 'EC1A 1BB', ''],
            ['Sarah', 'Reid', 'sarah.reid@demo.test', '07700900003', '90210', 'CA'],
            ['Tom', 'Hayes', 'tom.hayes@demo.test', '07700900004', '73301', 'TX'],
            ['Emma', 'Walsh', 'emma.walsh@demo.test', '07700900005', 'W1A 0AX', ''],
        ];

        foreach ($samples as $i => [$first, $last, $email, $phone, $zip, $state]) {
            $fields = [
                'firstname' => $first,
                'lastname' => $last,
                'email' => $email,
                'phone1' => $phone,
                'zipcode' => $zip,
            ];
            if ($state) {
                $fields['state'] = $state;
            }

            $lead = Lead::create([
                'account_id' => $account->id,
                'campaign_id' => $campaign->id,
                'supplier_id' => $supplier->id,
                'source_id' => $source->id,
                'sold_to_buyer_id' => $buyer->id,
                'status' => LeadStatus::Sold,
                'field_data' => $fields,
                'sid' => $source->sid,
                'received_at' => now()->subHours($i + 1),
                'distributed_at' => now()->subHours($i + 1),
            ]);

            LeadFinancial::create([
                'lead_id' => $lead->id,
                'revenue' => 15,
                'payout' => 5,
                'margin' => 10,
                'currency' => $campaign->currency,
            ]);

            LeadEvent::create([
                'lead_id' => $lead->id,
                'event_type' => $i === 0 ? 'sold' : 'processed',
                'level' => 'info',
                'message' => $i === 0 ? 'Lead sold to buyer via distribution' : 'Lead processed through delivery pipeline',
            ]);
        }

        $pendingLead = Lead::create([
            'account_id' => $account->id,
            'campaign_id' => $campaign->id,
            'supplier_id' => $supplier->id,
            'source_id' => $source->id,
            'status' => LeadStatus::Pending,
            'field_data' => [
                'firstname' => 'Queue',
                'lastname' => 'Test',
                'email' => 'queue.test@demo.test',
                'phone1' => '07700900099',
                'zipcode' => 'AB1 2CD',
            ],
            'sid' => $source->sid,
            'received_at' => now(),
        ]);

        if (config('queue.default') !== 'sync') {
            ProcessLeadJob::dispatch($pendingLead->id);
        }
    }
}
