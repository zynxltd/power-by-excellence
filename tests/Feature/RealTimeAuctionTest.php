<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\Lead;
use App\Services\Distribution\DistributionEngine;
use App\Services\Leads\LeadPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RealTimeAuctionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
    }

    public function test_parallel_auction_pings_all_and_posts_only_to_highest_bidder(): void
    {
        Http::fake([
            '*/api/v1/ping' => Http::response(['Success' => true, 'Cost' => 20, 'PingID' => 'p1'], 200),
            '*/api/v1/post' => Http::response(['Success' => true, 'Approved' => true], 200),
        ]);

        $campaign = Campaign::where('reference', 'loans-uk')->first();
        $this->assertNotNull($campaign);
        $this->assertSame('real_time_auction', $campaign->bidding_mode);

        $deliveries = Delivery::where('campaign_id', $campaign->id)
            ->where('method', 'ping_post')
            ->get();

        $this->assertGreaterThanOrEqual(2, $deliveries->count());

        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Accepted,
            'field_data' => [
                'firstname' => 'Auction',
                'lastname' => 'Test',
                'email' => 'auction.test@demo.test',
                'phone1' => '07700900111',
                'zipcode' => 'SW1A 1AA',
                'loan_amount' => 5000,
            ],
            'received_at' => now(),
        ]);

        $result = app(DistributionEngine::class)->distribute($lead->fresh());

        $this->assertTrue($result->sold);

        $successPosts = DeliveryLog::where('lead_id', $lead->id)->where('status', 'success')->count();
        $this->assertSame(1, $successPosts, 'Only the auction winner should complete a successful post');

        $outbid = DeliveryLog::where('lead_id', $lead->id)->where('status', 'outbid')->count();
        $this->assertGreaterThanOrEqual(1, $outbid);
    }

    public function test_uk_platform_seeds_all_five_verticals(): void
    {
        $refs = ['auto-insurance-uk', 'loans-uk', 'mortgage-uk', 'payday-loans-uk', 'solar-uk'];

        foreach ($refs as $ref) {
            $this->assertDatabaseHas('campaigns', ['reference' => $ref]);
        }

        $this->assertDatabaseHas('campaigns', ['vertical_id' => 'payday_loans', 'reference' => 'payday-loans-uk']);
    }

    public function test_loans_campaign_processes_through_pipeline(): void
    {
        Http::fake([
            '*/api/v1/ping' => Http::response(['Success' => true, 'Cost' => 18, 'PingID' => 'p2'], 200),
            '*/api/v1/post' => Http::response(['Success' => true, 'Approved' => true], 200),
        ]);

        $campaign = Campaign::where('reference', 'mortgage-uk')->first();

        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Pending,
            'field_data' => [
                'firstname' => 'Mort',
                'lastname' => 'Gage',
                'email' => 'mortgage.test@demo.test',
                'phone1' => '07700900222',
                'zipcode' => 'EC1A 1BB',
                'property_value' => 350000,
                'loan_amount' => 250000,
            ],
            'received_at' => now(),
        ]);

        app(LeadPipeline::class)->process($lead->fresh());

        $lead->refresh();
        $this->assertSame(LeadStatus::Sold, $lead->status);
    }
}
