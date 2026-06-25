<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\Lead;
use App\Models\User;
use App\Services\Buyers\BuyerEligibilityService;
use App\Services\Caps\CapService;
use Database\Seeders\PlatformSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BuyerEligibilityFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected Account $account;

    protected Campaign $campaign;

    protected Buyer $buyer;

    protected Delivery $delivery;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::create([
            'name' => 'Eligibility Test',
            'slug' => 'eligibility-test',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
            'is_active' => true,
            'settings' => ['require_buyer_prepay' => true],
        ]);

        $this->campaign = Campaign::create([
            'account_id' => $this->account->id,
            'name' => 'Test Campaign',
            'reference' => 'elig-campaign',
            'status' => 'active',
            'country' => 'GB',
            'currency' => 'GBP',
            'sell_mode' => 'multi_sell',
            'payout_amount' => 5,
            'floor_price' => 0,
        ]);

        $this->buyer = Buyer::create([
            'account_id' => $this->account->id,
            'reference' => 'elig-buyer',
            'name' => 'Elig Buyer',
            'status' => 'active',
            'credit_balance' => 100,
            'caps' => ['daily' => 1],
        ]);

        $this->delivery = Delivery::create([
            'campaign_id' => $this->campaign->id,
            'buyer_id' => $this->buyer->id,
            'name' => 'Test Delivery',
            'method' => 'direct_post',
            'status' => 'active',
            'trigger_type' => 'on_lead_arrival',
            'revenue_type' => 'fixed',
            'revenue_amount' => 20,
            'config' => ['url' => 'https://example.com/post'],
        ]);
    }

    public function test_buyer_volume_cap_blocks_second_delivery(): void
    {
        app(CapService::class)->increment('buyer', $this->buyer->id, $this->buyer->caps);

        $lead = $this->makeLead(['quality_score' => 90]);
        $service = app(BuyerEligibilityService::class);

        $this->assertFalse($service->canDeliver($lead, $this->delivery->fresh(['buyer'])));
    }

    public function test_exclusive_only_buyer_skips_multi_sell_campaign(): void
    {
        $this->buyer->update(['settings' => ['exclusive_only' => true]]);

        $lead = $this->makeLead(['quality_score' => 90]);
        $service = app(BuyerEligibilityService::class);

        $this->assertFalse($service->canDeliver($lead, $this->delivery->fresh(['buyer'])));
    }

    public function test_min_quality_score_blocks_low_quality_leads(): void
    {
        $this->buyer->update(['settings' => ['min_quality_score' => 80]]);

        $lead = $this->makeLead(['quality_score' => 60]);
        $service = app(BuyerEligibilityService::class);

        $this->assertFalse($service->canDeliver($lead, $this->delivery->fresh(['buyer'])));
    }

    public function test_spend_cap_blocks_when_daily_limit_reached(): void
    {
        $this->buyer->update(['caps' => ['daily_spend_cap' => 25]]);
        app(CapService::class)->incrementSpend('buyer', $this->buyer->id, $this->buyer->caps, 20);

        $lead = $this->makeLead(['quality_score' => 90]);
        $service = app(BuyerEligibilityService::class);

        $this->assertFalse($service->canDeliver($lead, $this->delivery->fresh(['buyer'])));
    }

    public function test_out_of_schedule_buyer_is_excluded(): void
    {
        $this->buyer->update([
            'schedule' => [
                'enabled' => true,
                'timezone' => 'UTC',
                'start' => '23:59',
                'end' => '23:58',
            ],
        ]);

        $lead = $this->makeLead(['quality_score' => 90]);
        $service = app(BuyerEligibilityService::class);

        $this->assertFalse($service->canDeliver($lead, $this->delivery->fresh(['buyer'])));
    }

    public function test_campaign_spend_cap_blocks_when_daily_budget_reached(): void
    {
        $this->campaign->update(['caps' => ['daily_spend_cap' => 25]]);
        app(CapService::class)->incrementSpend('campaign', $this->campaign->id, $this->campaign->caps, 20);

        $lead = $this->makeLead(['quality_score' => 90]);
        $service = app(BuyerEligibilityService::class);

        $this->assertFalse($service->canDeliver($lead, $this->delivery->fresh(['buyer'])));
    }

    protected function makeLead(array $metadata = []): Lead
    {
        return Lead::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'queue_id' => 'q-'.uniqid(),
            'status' => LeadStatus::Accepted,
            'field_data' => ['email' => 'test@example.com', 'firstname' => 'Test'],
            'metadata' => $metadata,
            'received_at' => now(),
        ])->load('campaign');
    }
}