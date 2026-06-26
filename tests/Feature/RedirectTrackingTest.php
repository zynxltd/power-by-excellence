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
use App\Models\User;
use App\Services\Api\ApiKeyService;
use App\Services\Distribution\DistributionEngine;
use App\Services\Reports\ReportMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class RedirectTrackingTest extends TestCase
{
    use RefreshDatabase;

    private function seedSoldLeadWithRedirect(): array
    {
        $account = Account::create([
            'name' => 'Redirect Tracking',
            'slug' => 'redirect-tracking',
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
            'tier' => 1,
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

        return compact('account', 'campaign', 'buyer', 'delivery', 'lead');
    }

    public function test_sale_records_redirect_offer_and_api_returns_tracked_url(): void
    {
        ['lead' => $lead] = $this->seedSoldLeadWithRedirect();
        $lead = $lead->fresh();

        $this->assertSame(LeadStatus::Sold, $lead->status);
        $this->assertSame('https://tier.example/thanks', $lead->redirect_url);
        $this->assertNotNull($lead->redirect_offered_at);
        $this->assertNotNull($lead->winning_delivery_id);

        $key = app(ApiKeyService::class)->create([
            'account_id' => $lead->account_id,
            'name' => 'Read Key',
            'type' => 'administrator',
            'permissions' => ['leads.read'],
        ]);

        $this->getJson('/api/v1/leads/'.$lead->uuid, [
            'Authorization' => 'Bearer '.$key['token'],
        ])
            ->assertOk()
            ->assertJsonPath('redirect_url', url('/r/'.$lead->uuid));
    }

    public function test_public_redirect_endpoint_records_follow_and_redirects(): void
    {
        ['lead' => $lead] = $this->seedSoldLeadWithRedirect();
        $lead = $lead->fresh();

        $this->get('/r/'.$lead->uuid)
            ->assertRedirect('https://tier.example/thanks');

        $lead->refresh();
        $this->assertNotNull($lead->redirect_followed_at);

        $this->get('/r/'.$lead->uuid)
            ->assertRedirect('https://tier.example/thanks');
    }

    public function test_reports_include_redirect_rate_for_buyers_and_tiers(): void
    {
        ['account' => $account, 'lead' => $lead, 'buyer' => $buyer, 'delivery' => $delivery] = $this->seedSoldLeadWithRedirect();
        $lead = $lead->fresh();

        $this->get('/r/'.$lead->uuid)->assertRedirect();

        $user = User::factory()->create(['account_id' => $account->id, 'role' => 'account_admin']);

        $request = Request::create('/reports', 'GET', ['days' => 28]);
        $request->setUserResolver(fn () => $user);
        $request->attributes->set('account', $account);

        $metrics = ReportMetrics::fromRequest($request);
        $summary = $metrics->summary($metrics->dailyCharts());

        $this->assertSame(1, $summary['redirect']['offered']);
        $this->assertSame(1, $summary['redirect']['followed']);
        $this->assertSame(100.0, $summary['redirect']['redirect_rate']);

        $buyerRow = $metrics->byBuyer()->items()[0];
        $this->assertSame($buyer->id, $buyerRow->buyer_id);
        $this->assertSame(1, (int) $buyerRow->redirects_offered);
        $this->assertSame(1, (int) $buyerRow->redirects_followed);

        $tierRow = $metrics->tierSummary()->items()[0];
        $this->assertSame($delivery->tier, $tierRow->tier);
        $this->assertSame(1, (int) $tierRow->redirects_offered);
        $this->assertSame(1, (int) $tierRow->redirects_followed);

        $deliveryRow = $metrics->deliveryPerformance()->items()[0];
        $this->assertSame($delivery->id, $deliveryRow->delivery_id);
        $this->assertSame(1, (int) $deliveryRow->redirects_offered);
        $this->assertSame(1, (int) $deliveryRow->redirects_followed);
    }
}
