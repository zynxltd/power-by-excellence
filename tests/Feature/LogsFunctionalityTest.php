<?php

namespace Tests\Feature;

use App\Models\AccessLog;
use App\Models\Account;
use App\Models\AccountAuditLog;
use App\Models\ApiRequestLog;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\Lead;
use App\Models\LeadEvent;
use App\Models\User;
use App\Services\Api\ApiKeyService;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LogsFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->account = Account::where('slug', 'excellence-uk')->first();
    }

    protected function tenantRequest()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_log_routes_map_to_logs_module(): void
    {
        $this->assertSame('logs', AdminModules::moduleForRoute('logs.access'));
        $this->assertSame('logs', AdminModules::moduleForRoute('logs.delivery'));
        $this->assertSame('logs', AdminModules::moduleForRoute('logs.api'));
        $this->assertSame('logs', AdminModules::moduleForRoute('logs.changes'));
        $this->assertSame('logs', AdminModules::moduleForRoute('logs.security'));
    }

    public function test_access_logs_index_filters_by_action(): void
    {
        AccessLog::create([
            'account_id' => $this->account->id,
            'user_id' => $this->admin->id,
            'action' => 'login',
            'ip_address' => '10.0.0.1',
            'path' => '/login',
        ]);

        AccessLog::create([
            'account_id' => $this->account->id,
            'user_id' => $this->admin->id,
            'action' => 'failed',
            'ip_address' => '10.0.0.2',
            'path' => '/login',
        ]);

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('logs.access', ['action' => 'failed']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Logs/AccessLogs')
                ->has('logs')
                ->where('filters.action', 'failed')
                ->where('logs.total', 1)
            );
    }

    public function test_access_logs_are_scoped_to_tenant(): void
    {
        $other = Account::where('slug', 'insurance-ca')->first();

        AccessLog::create([
            'account_id' => $other->id,
            'user_id' => $this->admin->id,
            'action' => 'login',
            'ip_address' => '10.0.0.99',
            'path' => '/login',
        ]);

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('logs.access'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('logs.data', fn ($rows) => collect($rows)->pluck('ip_address')->doesntContain('10.0.0.99'))
            );
    }

    public function test_api_request_logs_index_and_error_filter(): void
    {
        ApiRequestLog::create([
            'account_id' => $this->account->id,
            'method' => 'POST',
            'path' => '/api/v1/leads',
            'status_code' => 202,
            'duration_ms' => 45,
            'ip_address' => '127.0.0.1',
        ]);

        ApiRequestLog::create([
            'account_id' => $this->account->id,
            'method' => 'POST',
            'path' => '/api/v1/leads',
            'status_code' => 422,
            'duration_ms' => 12,
            'error_message' => 'Validation failed',
            'ip_address' => '127.0.0.1',
        ]);

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('logs.api'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Logs/Api')
                ->has('logs')
                ->has('stats')
                ->where('stats.total', fn ($count) => $count >= 2)
            );

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('logs.api', ['status' => 'error']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('logs.data', fn ($rows) => collect($rows)->every(fn ($row) => $row['status_code'] >= 400))
            );
    }

    public function test_api_request_logs_are_scoped_to_tenant(): void
    {
        $other = Account::where('slug', 'insurance-ca')->first();

        ApiRequestLog::withoutGlobalScopes()->create([
            'account_id' => $other->id,
            'method' => 'GET',
            'path' => '/api/v1/leads/foreign',
            'status_code' => 200,
            'duration_ms' => 5,
            'ip_address' => '127.0.0.1',
        ]);

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('logs.api'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('logs.data', fn ($rows) => collect($rows)->pluck('path')->doesntContain('/api/v1/leads/foreign'))
            );
    }

    public function test_api_ingest_creates_request_log_entry(): void
    {
        $token = app(ApiKeyService::class)->create([
            'account_id' => $this->account->id,
            'name' => 'Logs test key',
            'type' => 'administrator',
            'permissions' => ['leads.create'],
        ])['token'];

        $campaign = Campaign::where('reference', 'loans-uk')->first();

        $this->postJson('/api/v1/leads', [
            'campaign_reference' => $campaign->reference,
            'firstname' => 'Log',
            'email' => 'log.test.'.uniqid().'@example.com',
            'phone1' => '07700900123',
            'zipcode' => 'SW1A 1AA',
        ], ['Authorization' => 'Bearer '.$token])
            ->assertStatus(202);

        $this->assertDatabaseHas('api_request_logs', [
            'account_id' => $this->account->id,
            'path' => '/api/v1/leads',
            'status_code' => 202,
        ]);
    }

    public function test_change_logs_list_lead_events_for_tenant(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();
        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => 'accepted',
            'field_data' => ['email' => 'changelog@test.test'],
            'received_at' => now(),
        ]);

        LeadEvent::create([
            'lead_id' => $lead->id,
            'event_type' => 'lead.ingested',
            'level' => 'info',
            'message' => 'Lead ingested for change log test',
            'payload' => ['source' => 'test'],
        ]);

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('logs.changes'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Logs/ChangeLogs')
                ->has('events')
                ->where('events.data', fn ($rows) => collect($rows)->pluck('event_type')->contains('lead.ingested'))
            );
    }

    public function test_change_logs_exclude_other_tenant_events(): void
    {
        $otherCampaign = Campaign::withoutGlobalScopes()
            ->whereHas('account', fn ($q) => $q->where('slug', 'insurance-ca'))
            ->first();

        $otherLead = Lead::withoutGlobalScopes()->create([
            'account_id' => $otherCampaign->account_id,
            'campaign_id' => $otherCampaign->id,
            'status' => 'accepted',
            'field_data' => ['email' => 'foreign-changelog@test.test'],
            'received_at' => now(),
        ]);

        LeadEvent::create([
            'lead_id' => $otherLead->id,
            'event_type' => 'lead.foreign_only',
            'level' => 'info',
            'message' => 'Should not appear for UK tenant',
        ]);

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('logs.changes'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('events.data', fn ($rows) => collect($rows)->pluck('event_type')->doesntContain('lead.foreign_only'))
            );
    }

    public function test_security_logs_page_scoped_to_tenant(): void
    {
        $other = Account::where('slug', 'insurance-ca')->first();

        AccessLog::create([
            'account_id' => $other->id,
            'user_id' => $this->admin->id,
            'action' => 'login',
            'ip_address' => '203.0.113.50',
            'path' => '/login',
        ]);

        AccountAuditLog::create([
            'account_id' => $other->id,
            'user_id' => $this->admin->id,
            'action' => 'campaign.updated',
            'entity_type' => 'campaign',
            'entity_id' => 1,
            'changes' => ['name' => 'Foreign'],
            'ip_address' => '203.0.113.50',
        ]);

        AccessLog::create([
            'account_id' => $this->account->id,
            'user_id' => $this->admin->id,
            'action' => 'login',
            'ip_address' => '198.51.100.10',
            'path' => '/login',
        ]);

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('logs.security'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Logs/Security')
                ->has('accessLogs')
                ->has('auditLogs')
                ->has('stats')
                ->where('accessLogs.data', fn ($rows) => collect($rows)->pluck('ip_address')->doesntContain('203.0.113.50'))
                ->where('auditLogs.data', fn ($rows) => collect($rows)->pluck('ip_address')->doesntContain('203.0.113.50'))
            );
    }

    public function test_delivery_logs_filter_by_status_and_campaign(): void
    {
        $campaign = Campaign::where('reference', 'loans-uk')->first();
        $otherCampaign = Campaign::where('account_id', $this->account->id)
            ->where('id', '!=', $campaign->id)
            ->first();
        $delivery = Delivery::where('campaign_id', $campaign->id)->first();
        $otherDelivery = Delivery::where('campaign_id', $otherCampaign->id)->first();

        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'status' => 'unsold',
            'field_data' => ['email' => 'delivery-log@test.test'],
            'received_at' => now(),
        ]);

        $successLog = DeliveryLog::create([
            'lead_id' => $lead->id,
            'delivery_id' => $delivery->id,
            'buyer_id' => $delivery->buyer_id,
            'status' => 'success',
            'duration_ms' => 100,
        ]);

        $otherLead = Lead::create([
            'account_id' => $otherCampaign->account_id,
            'campaign_id' => $otherCampaign->id,
            'status' => 'unsold',
            'field_data' => ['email' => 'other-campaign-log@test.test'],
            'received_at' => now(),
        ]);

        DeliveryLog::create([
            'lead_id' => $otherLead->id,
            'delivery_id' => $otherDelivery->id,
            'buyer_id' => $otherDelivery->buyer_id,
            'status' => 'failed',
            'duration_ms' => 200,
        ]);

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('logs.delivery', ['status' => 'success', 'campaign_id' => $campaign->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stats.total', 1)
                ->where('logs.data.0.id', $successLog->id)
            );
    }

    public function test_delivery_log_show_blocks_cross_tenant_access(): void
    {
        $otherCampaign = Campaign::withoutGlobalScopes()
            ->whereHas('account', fn ($q) => $q->where('slug', 'insurance-ca'))
            ->first();
        $delivery = Delivery::withoutGlobalScopes()->where('campaign_id', $otherCampaign->id)->first();
        $lead = Lead::withoutGlobalScopes()->create([
            'account_id' => $otherCampaign->account_id,
            'campaign_id' => $otherCampaign->id,
            'status' => 'accepted',
            'field_data' => ['email' => 'cross-log@test.test'],
            'received_at' => now(),
        ]);

        $log = DeliveryLog::create([
            'lead_id' => $lead->id,
            'delivery_id' => $delivery->id,
            'buyer_id' => $delivery->buyer_id,
            'status' => 'success',
            'duration_ms' => 50,
        ]);

        $this->tenantRequest()
            ->actingAs($this->admin)
            ->get(route('logs.delivery.show', $log))
            ->assertNotFound();
    }
}
