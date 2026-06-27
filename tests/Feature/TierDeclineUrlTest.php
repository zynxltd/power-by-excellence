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
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TierDeclineUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_returns_decline_url_when_all_tiers_fail(): void
    {
        Http::fake(['https://fail.test/*' => Http::response(['Success' => false], 422)]);

        $account = Account::create([
            'name' => 'Decline Test',
            'slug' => 'decline-test',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
        ]);

        $campaign = Campaign::create([
            'account_id' => $account->id,
            'name' => 'Decline Campaign',
            'reference' => 'decline-campaign',
            'status' => 'active',
            'sell_mode' => 'exclusive',
            'payout_amount' => 5,
            'use_advanced_distribution' => true,
            'validation_config' => ['quarantine_unsold' => false],
        ]);

        $buyer = Buyer::create([
            'account_id' => $account->id,
            'reference' => 'decline-buyer',
            'name' => 'Decline Buyer',
            'status' => 'active',
            'credit_balance' => 500,
        ]);

        $delivery = Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => 'Always Reject',
            'method' => DeliveryMethod::DirectPost,
            'status' => 'active',
            'priority' => 1,
            'revenue_amount' => 20,
            'config' => [
                'url' => 'https://fail.test/post',
            ],
        ]);

        DistributionConfig::create([
            'campaign_id' => $campaign->id,
            'name' => 'Decline tree',
            'is_active' => true,
            'config' => [
                'decline_url' => 'https://example.com/declined',
                'groups' => [
                    [
                        'name' => 'Tier 1',
                        'mode' => 'waterfall',
                        'delivery_ids' => [$delivery->id],
                    ],
                ],
            ],
        ]);

        $lead = Lead::create([
            'account_id' => $account->id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Accepted,
            'field_data' => ['email' => 'decline@test.com', 'firstname' => 'De', 'lastname' => 'Cline'],
            'received_at' => now(),
        ]);

        app(DistributionEngine::class)->distribute($lead->fresh());

        $this->assertSame(LeadStatus::Unsold, $lead->fresh()->status);

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
            ->assertJsonPath('status', LeadStatus::Unsold->value)
            ->assertJsonPath('redirect_url', null)
            ->assertJsonPath('decline_url', url('/r/'.$lead->fresh()->uuid));
    }

    public function test_distribution_config_persists_decline_url(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();

        $admin = \App\Models\User::where('email', 'uk@powerbyexcellence.test')->first();
        $campaign = Campaign::where('reference', 'auto-insurance-uk')->first();
        $delivery = Delivery::where('campaign_id', $campaign->id)->first();

        $this->actingAs($admin)
            ->post(route('distribution.store'), [
                'campaign_id' => $campaign->id,
                'name' => 'Decline URL Tree',
                'is_active' => true,
                'decline_url' => 'https://example.com/no-buyers',
                'groups' => [
                    [
                        'name' => 'Tier 1',
                        'mode' => 'waterfall',
                        'delivery_ids' => [$delivery->id],
                    ],
                ],
            ])
            ->assertRedirect();

        $config = DistributionConfig::where('name', 'Decline URL Tree')->first();
        $this->assertSame('https://example.com/no-buyers', $config->config['decline_url']);
    }
}
