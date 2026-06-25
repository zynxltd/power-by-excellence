<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\Lead;
use App\Models\User;
use App\Enums\DeliveryMethod;
use App\Models\CampaignField;
use App\Services\Api\ApiKeyService;
use App\Services\Leads\LeadPipeline;
use App\Support\AdminModules;
use App\Support\LeadQueueMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LeadPipelineFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    protected User $ukAdmin;

    protected Account $ukAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->ukAdmin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->ukAccount = Account::where('slug', 'excellence-uk')->first();
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_leads_routes_map_to_operations_module(): void
    {
        $this->assertSame('operations', AdminModules::moduleForRoute('leads.index'));
        $this->assertSame('operations', AdminModules::moduleForRoute('leads.show'));
    }

    protected function pipelineCampaign(): Campaign
    {
        $campaign = Campaign::create([
            'account_id' => $this->ukAccount->id,
            'name' => 'Pipeline QA',
            'reference' => 'pipeline-qa-'.Str::random(6),
            'status' => 'active',
            'payout_amount' => 5,
            'floor_price' => 10,
            'currency' => 'GBP',
        ]);

        foreach (['firstname', 'lastname', 'email', 'phone1', 'zipcode'] as $i => $name) {
            CampaignField::create([
                'campaign_id' => $campaign->id,
                'name' => $name,
                'required' => true,
                'sort_order' => $i,
            ]);
        }

        $buyer = Buyer::where('account_id', $this->ukAccount->id)->first();

        Delivery::create([
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => 'Pipeline Store',
            'method' => DeliveryMethod::StoreLead,
            'status' => 'active',
            'priority' => 1,
            'revenue_type' => 'fixed',
            'revenue_amount' => 20,
        ]);

        return $campaign;
    }

    public function test_sync_ingest_runs_full_pipeline_to_sold(): void
    {
        $campaign = $this->pipelineCampaign();
        $token = app(ApiKeyService::class)->create([
            'account_id' => $this->ukAccount->id,
            'name' => 'Pipeline sync',
            'type' => 'administrator',
            'permissions' => ['leads.create'],
        ])['token'];

        $email = 'pipeline-sync.'.uniqid().'@example.com';

        $this->postJson('/api/v1/leads', [
            'campaign_reference' => $campaign->reference,
            'sync' => true,
            'firstname' => 'Pipe',
            'lastname' => 'Line',
            'email' => $email,
            'phone1' => '07700900111',
            'zipcode' => 'SW1A 1AA',
        ], ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('status', LeadStatus::Sold->value);

        $lead = Lead::where('field_data->email', $email)->first();
        $this->assertNotNull($lead);
        $this->assertNotNull($lead->processing_ms);
        $this->assertGreaterThan(0, $lead->processing_ms);
    }

    public function test_duplicate_lead_gets_duplicate_status_not_generic_rejected(): void
    {
        $campaign = $this->pipelineCampaign();
        $token = app(ApiKeyService::class)->create([
            'account_id' => $this->ukAccount->id,
            'name' => 'Dup test',
            'type' => 'administrator',
            'permissions' => ['leads.create'],
        ])['token'];

        $payload = [
            'campaign_reference' => $campaign->reference,
            'sync' => true,
            'firstname' => 'Dup',
            'lastname' => 'Lead',
            'email' => 'dup-pipeline.'.uniqid().'@example.com',
            'phone1' => '07700900222',
            'zipcode' => 'EC1A 1BB',
        ];

        $this->postJson('/api/v1/leads', $payload, ['Authorization' => 'Bearer '.$token])->assertOk();
        $this->postJson('/api/v1/leads', $payload, ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('status', LeadStatus::Duplicate->value);
    }

    public function test_inactive_campaign_rejects_lead_with_reason(): void
    {
        $campaign = Campaign::where('account_id', $this->ukAccount->id)->first();
        $campaign->update(['status' => 'inactive']);

        $lead = Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Pending,
            'field_data' => ['email' => 'inactive-campaign@test.test', 'phone1' => '07700900333'],
            'received_at' => now(),
        ]);

        app(LeadPipeline::class)->process($lead->fresh());

        $lead->refresh();
        $this->assertSame(LeadStatus::Rejected, $lead->status);
        $this->assertSame('Campaign inactive', $lead->reject_reason);
    }

    public function test_lead_index_pipeline_summary_counts_processing_from_real_statuses(): void
    {
        $campaign = Campaign::where('account_id', $this->ukAccount->id)->first();

        Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Validating,
            'field_data' => ['email' => 'validating@test.test'],
            'received_at' => now(),
        ]);

        Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Distributing,
            'field_data' => ['email' => 'distributing@test.test'],
            'received_at' => now(),
        ]);

        $response = $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('leads.index', ['campaign_id' => $campaign->id]))
            ->assertOk();

        $summary = $response->viewData('page')['props']['pipelineSummary'];
        $this->assertGreaterThanOrEqual(2, $summary['processing']);
    }

    public function test_queue_metrics_aggregate_validating_and_distributing_as_processing(): void
    {
        $counts = [
            LeadStatus::Pending->value => 3,
            LeadStatus::Validating->value => 2,
            LeadStatus::Distributing->value => 1,
            LeadStatus::Accepted->value => 4,
            LeadStatus::Quarantined->value => 2,
        ];

        $breakdown = LeadQueueMetrics::queueBreakdown($counts);

        $this->assertSame(3, $breakdown['pending']);
        $this->assertSame(3, $breakdown['processing']);
        $this->assertSame(4, $breakdown['accepted']);
        $this->assertSame(2, $breakdown['quarantined']);
    }

    public function test_lead_show_pipeline_stages_for_sold_lead(): void
    {
        $campaign = Campaign::where('account_id', $this->ukAccount->id)->first();
        $buyer = Buyer::where('account_id', $this->ukAccount->id)->first();

        $lead = Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $campaign->id,
            'sold_to_buyer_id' => $buyer->id,
            'status' => LeadStatus::Sold,
            'field_data' => ['email' => 'sold-stages@test.test'],
            'received_at' => now(),
            'distributed_at' => now(),
            'processing_ms' => 180,
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('leads.show', $lead))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('pipelineStages', 4)
                ->where('outcomeDetail.title', 'Sold')
                ->where('outcomeDetail.summary', fn ($s) => str_contains($s, $buyer->name))
            );

        $this->assertSame(180, $lead->processing_ms);
    }

    public function test_unsold_lead_outcome_includes_actionable_delivery_hints(): void
    {
        $campaign = Campaign::where('account_id', $this->ukAccount->id)->first();
        $buyer = Buyer::where('account_id', $this->ukAccount->id)->first();
        $delivery = Delivery::where('campaign_id', $campaign->id)->first();

        $lead = Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Unsold,
            'field_data' => ['email' => 'unsold-hints@test.test'],
            'received_at' => now(),
        ]);

        DeliveryLog::create([
            'lead_id' => $lead->id,
            'delivery_id' => $delivery->id,
            'buyer_id' => $buyer->id,
            'status' => 'outbid',
            'duration_ms' => 90,
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('leads.show', $lead))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('outcomeDetail.title', 'Unsold')
                ->where('outcomeDetail.hints', fn ($hints) => collect($hints)->contains(fn ($h) => str_contains($h, 'outbid')))
            );
    }

    public function test_lead_show_blocks_validation_quarantine_release(): void
    {
        $campaign = Campaign::where('account_id', $this->ukAccount->id)->first();
        $lead = Lead::create([
            'account_id' => $this->ukAccount->id,
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

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('leads.quarantine.release', $lead))
            ->assertStatus(422);

        $this->assertSame(LeadStatus::Quarantined, $lead->fresh()->status);
    }

    public function test_api_lead_read_rejects_cross_tenant_uuid(): void
    {
        $otherAccount = Account::where('slug', 'partner-solar-us')->first();
        $otherCampaign = Campaign::withoutGlobalScopes()->where('account_id', $otherAccount->id)->first();

        $otherLead = Lead::withoutGlobalScopes()->create([
            'account_id' => $otherAccount->id,
            'campaign_id' => $otherCampaign->id,
            'uuid' => (string) Str::uuid(),
            'status' => LeadStatus::Sold,
            'field_data' => ['email' => 'cross-tenant-lead@test.test'],
            'received_at' => now(),
        ]);

        $token = app(ApiKeyService::class)->create([
            'account_id' => $this->ukAccount->id,
            'name' => 'UK read',
            'type' => 'administrator',
            'permissions' => ['leads.read'],
        ])['token'];

        $this->getJson('/api/v1/leads/'.$otherLead->uuid, ['Authorization' => 'Bearer '.$token])
            ->assertNotFound();
    }

    public function test_processing_filter_on_lead_index_matches_validating_and_distributing(): void
    {
        $campaign = Campaign::where('account_id', $this->ukAccount->id)->first();

        $validating = Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Validating,
            'field_data' => ['email' => 'filter-validating@test.test'],
            'received_at' => now(),
        ]);

        Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Sold,
            'field_data' => ['email' => 'filter-sold@test.test'],
            'received_at' => now(),
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('leads.index', ['status' => 'processing', 'campaign_id' => $campaign->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('leads.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['id'] === $validating->id)
                    && collect($rows)->every(fn ($row) => in_array($row['status'], LeadStatus::processingValues(), true)))
            );
    }

    public function test_duplicate_filter_on_lead_index(): void
    {
        $campaign = Campaign::where('account_id', $this->ukAccount->id)->first();

        $duplicate = Lead::create([
            'account_id' => $this->ukAccount->id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Duplicate,
            'reject_reason' => 'Duplicate email',
            'field_data' => ['email' => 'duplicate-filter@test.test'],
            'received_at' => now(),
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('leads.index', ['status' => 'duplicate', 'campaign_id' => $campaign->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('leads.data', fn ($rows) => count($rows) === 1 && $rows[0]['id'] === $duplicate->id)
            );
    }
}
