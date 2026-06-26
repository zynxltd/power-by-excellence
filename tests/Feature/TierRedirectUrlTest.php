<?php

namespace Tests\Feature;

use App\Enums\DeliveryMethod;
use App\Enums\LeadStatus;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DistributionConfig;
use App\Models\Lead;
use App\Services\Api\ApiKeyService;
use App\Services\Distribution\DistributionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TierRedirectUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_returns_tier_redirect_url_when_lead_sells(): void
    {
        $account = Account::create([
            'name' => 'Redirect Test',
            'slug' => 'redirect-test',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
        ]);

        $campaign = Campaign::create([
            'account_id' => $account->id,
            'name' => 'Redirect Campaign',
            'reference' => 'redirect-campaign',
            'status' => 'active',
            'sell_mode' => 'exclusive',
            'payout_amount' => 5,
            'use_advanced_distribution' => true,
        ]);

        $buyer = Buyer::create([
            'account_id' => $account->id,
            'reference' => 'redirect-buyer',
            'name' => 'Redirect Buyer',
            'status' => 'active',
            'credit_balance' => 500,
        ]);

        $delivery = Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => 'Store',
            'method' => DeliveryMethod::StoreLead,
            'status' => 'active',
            'priority' => 1,
            'revenue_amount' => 20,
            'config' => ['redirect_url' => 'https://delivery.example/thanks'],
        ]);

        DistributionConfig::create([
            'campaign_id' => $campaign->id,
            'name' => 'Redirect tree',
            'is_active' => true,
            'config' => [
                'groups' => [
                    [
                        'name' => 'Tier 1',
                        'mode' => 'waterfall',
                        'redirect_url' => 'https://tier.example/thanks',
                        'delivery_ids' => [$delivery->id],
                    ],
                ],
            ],
        ]);

        $lead = Lead::create([
            'account_id' => $account->id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Accepted,
            'field_data' => ['email' => 'redirect@test.com', 'firstname' => 'Re', 'lastname' => 'Direct'],
            'received_at' => now(),
        ]);

        app(DistributionEngine::class)->distribute($lead->fresh());

        $key = app(ApiKeyService::class)->create([
            'account_id' => $account->id,
            'name' => 'Read Key',
            'type' => 'administrator',
            'permissions' => ['leads.read'],
        ]);

        $this->getJson('/api/v1/leads/'.$lead->fresh()->uuid, [
            'Authorization' => 'Bearer '.$key['token'],
        ])
            ->assertOk()
            ->assertJsonPath('status', LeadStatus::Sold->value)
            ->assertJsonPath('redirect_url', 'https://tier.example/thanks');
    }
}
