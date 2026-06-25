<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Account;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationsFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected string $tenantHost = 'excellence-uk.powerbyexcellence.test';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
    }

    protected function tenantRequest()
    {
        return $this->withServerVariables(['HTTP_HOST' => $this->tenantHost]);
    }

    public function test_operations_page_loads_with_stats_and_delivery_preview(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();
        $delivery = Delivery::where('campaign_id', $campaign->id)->first();

        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Accepted,
            'field_data' => ['email' => 'ops@test.test'],
            'received_at' => now(),
        ]);

        DeliveryLog::create([
            'lead_id' => $lead->id,
            'delivery_id' => $delivery->id,
            'buyer_id' => $delivery->buyer_id,
            'status' => 'success',
            'duration_ms' => 120,
        ]);

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('operations.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Operations/Index')
                ->has('stats', fn (Assert $stats) => $stats
                    ->has('leads_today')
                    ->has('sold_today')
                    ->has('unsold_today')
                    ->has('pending')
                    ->has('quarantined')
                    ->has('rejected_today')
                    ->has('ping_posts_today')
                    ->has('failed_today')
                    ->has('revenue_today')
                )
                ->has('queueBreakdown')
                ->has('hourlyLeads')
                ->has('topCampaigns')
                ->has('recentLeads')
                ->has('deliveryPreview')
                ->has('campaignWorkflow')
                ->has('filters')
            );
    }

    public function test_operations_campaign_filter_scopes_quarantine_count(): void
    {
        $account = Account::where('slug', 'excellence-uk')->first();
        $campaignA = Campaign::where('reference', 'loans-uk')->first();
        $campaignB = Campaign::where('account_id', $account->id)
            ->where('id', '!=', $campaignA->id)
            ->first();

        $this->assertNotNull($campaignB);

        Lead::create([
            'account_id' => $account->id,
            'campaign_id' => $campaignA->id,
            'status' => LeadStatus::Quarantined,
            'field_data' => ['email' => 'filter-a@test.test'],
            'received_at' => now(),
            'quarantined_until' => now()->addDay(),
            'metadata' => ['quarantine_reason' => 'hold'],
        ]);

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('operations.index', ['campaign_id' => $campaignA->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.campaign_id', (string) $campaignA->id)
                ->where('stats.quarantined', fn ($count) => $count >= 1)
            );

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('operations.index', ['campaign_id' => $campaignB->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.campaign_id', (string) $campaignB->id)
                ->where('stats.quarantined', 0)
            );
    }

    public function test_live_stats_accepts_campaign_filter(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();
        $other = Campaign::where('account_id', $campaign->account_id)
            ->where('id', '!=', $campaign->id)
            ->first();

        $before = $this->actingAs($this->admin)
            ->getJson(route('live-stats', ['campaign_id' => $other->id]))
            ->assertOk()
            ->json('quarantined');

        Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Quarantined,
            'field_data' => ['email' => 'filtered-quarantine@test.test'],
            'received_at' => now(),
            'quarantined_until' => now()->addDay(),
            'metadata' => ['quarantine_reason' => 'hold'],
        ]);

        $filtered = $this->actingAs($this->admin)
            ->getJson(route('live-stats', ['campaign_id' => $other->id]))
            ->assertOk()
            ->json('quarantined');

        $this->assertSame($before, $filtered);

        $unfiltered = $this->actingAs($this->admin)
            ->getJson(route('live-stats'))
            ->assertOk()
            ->json('quarantined');

        $this->assertGreaterThanOrEqual($before + 1, $unfiltered);
    }

    public function test_admin_can_release_non_validation_quarantine_hold(): void
    {
        Queue::fake();

        $campaign = Campaign::where('reference', 'loans-uk')->first();
        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Quarantined,
            'field_data' => ['email' => 'release@test.test'],
            'received_at' => now(),
            'quarantined_until' => now()->addDay(),
            'metadata' => ['quarantine_reason' => 'out_of_hours'],
        ]);

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->post(route('quarantine.release', $lead, absolute: false))
            ->assertRedirect();

        $lead->refresh();
        $this->assertSame(LeadStatus::Accepted, $lead->status);
        $this->assertNull($lead->quarantined_until);
        Queue::assertPushed(\App\Jobs\ProcessLeadJob::class);
    }

    public function test_validation_quarantine_cannot_be_released(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();
        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Quarantined,
            'field_data' => ['email' => 'validation-hold@test.test'],
            'received_at' => now(),
            'quarantined_until' => now()->addDay(),
            'metadata' => [
                'quarantine_reason' => 'validation',
                'email_validation' => ['status' => 'invalid'],
            ],
        ]);

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->post(route('quarantine.release', $lead, absolute: false))
            ->assertStatus(422);

        $lead->refresh();
        $this->assertSame(LeadStatus::Quarantined, $lead->status);
    }

    public function test_bulk_release_skips_validation_holds(): void
    {
        Queue::fake();

        $campaign = Campaign::where('reference', 'loans-uk')->first();

        $releasable = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Quarantined,
            'field_data' => ['email' => 'bulk-release@test.test'],
            'received_at' => now(),
            'quarantined_until' => now()->addDay(),
            'metadata' => ['quarantine_reason' => 'hold'],
        ]);

        $validation = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Quarantined,
            'field_data' => ['email' => 'bulk-skip@test.test'],
            'received_at' => now(),
            'quarantined_until' => now()->addDay(),
            'metadata' => [
                'quarantine_reason' => 'validation',
                'email_validation' => ['status' => 'invalid'],
            ],
        ]);

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->post('/quarantine/bulk-release', ['lead_ids' => [$releasable->id, $validation->id]])
            ->assertRedirect()
            ->assertSessionHas('success', '1 lead(s) released from quarantine.');

        $releasable->refresh();
        $validation->refresh();

        $this->assertSame(LeadStatus::Accepted, $releasable->status);
        $this->assertSame(LeadStatus::Quarantined, $validation->status);
        Queue::assertPushed(\App\Jobs\ProcessLeadJob::class, 1);
    }

    public function test_delivery_logs_index_and_show(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();
        $delivery = Delivery::where('campaign_id', $campaign->id)->first();
        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Unsold,
            'field_data' => ['email' => 'log@test.test'],
            'received_at' => now(),
        ]);

        $log = DeliveryLog::create([
            'lead_id' => $lead->id,
            'delivery_id' => $delivery->id,
            'buyer_id' => $delivery->buyer_id,
            'status' => 'failed',
            'ping_request' => ['email' => 'log@test.test'],
            'duration_ms' => 200,
        ]);

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('logs.delivery'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/DeliveryLogs/Index')
                ->has('logs')
                ->has('stats')
                ->has('filters')
            );

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('logs.delivery.show', $log))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/DeliveryLogs/Show')
                ->where('log.id', $log->id)
                ->where('log.status', 'failed')
            );
    }

    public function test_tenant_admin_cannot_release_other_tenant_quarantine_lead(): void
    {
        $otherCampaign = Campaign::withoutGlobalScopes()
            ->whereHas('account', fn ($q) => $q->where('slug', 'insurance-ca'))
            ->first();

        $lead = Lead::withoutGlobalScopes()->create([
            'account_id' => $otherCampaign->account_id,
            'campaign_id' => $otherCampaign->id,
            'status' => LeadStatus::Quarantined,
            'field_data' => ['email' => 'cross-tenant@test.test'],
            'received_at' => now(),
            'quarantined_until' => now()->addDay(),
            'metadata' => ['quarantine_reason' => 'hold'],
        ]);

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->post(route('quarantine.release', $lead, absolute: false))
            ->assertNotFound();
    }
}
