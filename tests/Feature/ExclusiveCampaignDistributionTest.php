<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\Lead;
use App\Services\Distribution\DistributionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExclusiveCampaignDistributionTest extends TestCase
{
    use RefreshDatabase;

    public function test_exclusive_campaign_stops_after_first_successful_sale(): void
    {
        Http::fake([
            'https://buyer-a.test/*' => Http::response(['Success' => true], 200),
            'https://buyer-b.test/*' => Http::response(['Success' => true], 200),
        ]);

        $account = Account::create([
            'name' => 'Exclusive Test',
            'slug' => 'exclusive-test',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
            'is_active' => true,
        ]);

        $campaign = Campaign::create([
            'account_id' => $account->id,
            'name' => 'Exclusive Campaign',
            'reference' => 'exclusive-campaign',
            'status' => 'active',
            'country' => 'GB',
            'currency' => 'GBP',
            'sell_mode' => 'exclusive',
            'payout_amount' => 5,
            'floor_price' => 0,
            'use_advanced_distribution' => false,
        ]);

        $buyerA = Buyer::create([
            'account_id' => $account->id,
            'reference' => 'buyer-a',
            'name' => 'Buyer A',
            'status' => 'active',
            'credit_balance' => 1000,
        ]);

        $buyerB = Buyer::create([
            'account_id' => $account->id,
            'reference' => 'buyer-b',
            'name' => 'Buyer B',
            'status' => 'active',
            'credit_balance' => 1000,
        ]);

        Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyerA->id,
            'name' => 'Delivery A',
            'method' => 'direct_post',
            'status' => 'active',
            'trigger_type' => 'on_lead_arrival',
            'priority' => 1,
            'revenue_type' => 'fixed',
            'revenue_amount' => 20,
            'config' => ['url' => 'https://buyer-a.test/post'],
        ]);

        Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyerB->id,
            'name' => 'Delivery B',
            'method' => 'direct_post',
            'status' => 'active',
            'trigger_type' => 'on_lead_arrival',
            'priority' => 2,
            'revenue_type' => 'fixed',
            'revenue_amount' => 15,
            'config' => ['url' => 'https://buyer-b.test/post'],
        ]);

        $lead = Lead::create([
            'account_id' => $account->id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Accepted,
            'field_data' => [
                'firstname' => 'Excl',
                'lastname' => 'Usive',
                'email' => 'exclusive@test.com',
                'phone1' => '07700900333',
                'zipcode' => 'SW1A 1AA',
            ],
            'received_at' => now(),
        ]);

        $result = app(DistributionEngine::class)->distribute($lead->fresh());

        $this->assertTrue($result->sold);
        $this->assertSame(LeadStatus::Sold, $lead->fresh()->status);

        $successCount = DeliveryLog::where('lead_id', $lead->id)->where('status', 'success')->count();
        $this->assertSame(1, $successCount, 'Exclusive campaign must sell to only one buyer');
    }
}
