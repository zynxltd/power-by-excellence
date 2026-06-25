<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Supplier;
use App\Models\Source;
use App\Services\Api\ApiKeyService;
use App\Services\Billing\AccountBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class ApiFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    protected Account $account;

    protected Campaign $campaign;

    protected Buyer $buyer;

    protected string $fullToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);

        $this->account = Account::where('slug', 'excellence-uk')->first();
        $this->campaign = Campaign::where('account_id', $this->account->id)->first();
        $this->buyer = Buyer::where('account_id', $this->account->id)->first();

        $this->fullToken = app(ApiKeyService::class)->create([
            'account_id' => $this->account->id,
            'name' => 'Full API Key',
            'type' => 'administrator',
            'permissions' => ['*'],
        ])['token'];
    }

    protected function apiToken(array $permissions): string
    {
        return app(ApiKeyService::class)->create([
            'account_id' => $this->account->id,
            'name' => 'Scoped '.implode(',', $permissions),
            'type' => 'administrator',
            'permissions' => $permissions,
        ])['token'];
    }

    protected function auth(array $permissions = ['*']): array
    {
        $token = $permissions === ['*'] ? $this->fullToken : $this->apiToken($permissions);

        return ['Authorization' => 'Bearer '.$token];
    }

    public function test_lead_ingest_async_and_sync(): void
    {
        Queue::fake();

        $this->postJson('/api/v1/leads', [
            'campaign_reference' => $this->campaign->reference,
            'firstname' => 'Api',
            'lastname' => 'Async',
            'email' => 'api.async.'.uniqid().'@example.com',
            'phone1' => '07700900123',
            'zipcode' => 'SW1A 1AA',
        ], $this->auth(['leads.create']))
            ->assertStatus(202)
            ->assertJsonStructure(['status', 'queue_id', 'lead_id']);

        Queue::assertPushed(\App\Jobs\ProcessLeadJob::class);

        $this->postJson('/api/v1/leads', [
            'campaign_reference' => $this->campaign->reference,
            'sync' => true,
            'firstname' => 'Api',
            'lastname' => 'Sync',
            'email' => 'api.sync.'.uniqid().'@example.com',
            'phone1' => '07700900456',
            'zipcode' => 'EC1A 1BB',
        ], $this->auth(['leads.create']))
            ->assertOk()
            ->assertJsonStructure(['status', 'lead_id', 'queue_id']);
    }

    public function test_supplier_key_attributes_supplier_and_sid(): void
    {
        $supplier = Supplier::where('account_id', $this->account->id)->first();
        $source = Source::where('supplier_id', $supplier->id)->first();

        $token = app(ApiKeyService::class)->create([
            'account_id' => $this->account->id,
            'supplier_id' => $supplier->id,
            'name' => 'Supplier ingest',
            'type' => 'supplier',
            'permissions' => ['leads.create', 'leads.read'],
        ])['token'];

        $response = $this->postJson('/api/v1/leads', [
            'campaign_reference' => $this->campaign->reference,
            'sid' => $source->sid,
            'sync' => true,
            'firstname' => 'Sup',
            'lastname' => 'plier',
            'email' => 'sup.'.uniqid().'@example.com',
            'phone1' => '07700900555',
            'zipcode' => 'W1A 0AX',
        ], ['Authorization' => 'Bearer '.$token]);

        $response->assertOk();
        $lead = Lead::where('uuid', $response->json('lead_id'))->first();
        $this->assertSame($supplier->id, $lead->supplier_id);
        $this->assertSame($source->sid, $lead->sid);
    }

    public function test_lead_read_endpoints(): void
    {
        $lead = Lead::where('account_id', $this->account->id)->first();

        $this->getJson('/api/v1/leads/'.$lead->uuid, $this->auth(['leads.read']))
            ->assertOk()
            ->assertJsonPath('lead_id', $lead->uuid);

        $this->getJson('/api/v1/leads/queue/'.$lead->queue_id, $this->auth(['leads.read']))
            ->assertOk()
            ->assertJsonPath('queue_id', $lead->queue_id);

        $this->postJson('/api/v1/leads/search', [
            'campaign_id' => $this->campaign->id,
            'per_page' => 5,
        ], $this->auth(['leads.read']))
            ->assertOk()
            ->assertJsonStructure(['data', 'current_page']);

        Queue::fake();

        $this->postJson('/api/v1/leads/'.$lead->uuid.'/reprocess', [], $this->auth(['leads.read']))
            ->assertOk()
            ->assertJsonStructure(['status', 'queue_id']);

        Queue::assertPushed(\App\Jobs\ProcessLeadJob::class);
    }

    public function test_lead_import_csv(): void
    {
        $csv = "firstname,lastname,email,phone1,zipcode\nImport,User,import.".uniqid()."@example.com,07700900666,SW1A 2AA\n";
        $file = UploadedFile::fake()->createWithContent('leads.csv', $csv);

        $this->post('/api/v1/leads/import', [
            'campaign_reference' => $this->campaign->reference,
            'file' => $file,
        ], $this->auth(['leads.create']))
            ->assertOk()
            ->assertJsonStructure(['import_id', 'status', 'success_rows', 'failed_rows']);
    }

    public function test_report_endpoints(): void
    {
        $this->getJson('/api/v1/reports/leads?from='.now()->subDays(7)->toDateString(), $this->auth(['reports.read']))
            ->assertOk()
            ->assertJsonStructure(['from', 'to', 'by_status', 'total']);

        $this->getJson('/api/v1/reports/revenue', $this->auth(['reports.read']))
            ->assertOk()
            ->assertJsonStructure(['from', 'to', 'revenue', 'payout', 'margin']);
    }

    public function test_quarantine_api_release_and_reject(): void
    {
        $releaseLead = Lead::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'uuid' => (string) Str::uuid(),
            'queue_id' => 'q_release_'.Str::random(8),
            'status' => LeadStatus::Quarantined,
            'field_data' => ['email' => 'release@api.test'],
            'received_at' => now(),
            'quarantined_until' => now()->addDay(),
        ]);

        $rejectLead = Lead::create([
            'account_id' => $this->account->id,
            'campaign_id' => $this->campaign->id,
            'uuid' => (string) Str::uuid(),
            'queue_id' => 'q_reject_'.Str::random(8),
            'status' => LeadStatus::Quarantined,
            'field_data' => ['email' => 'reject@api.test'],
            'received_at' => now(),
            'quarantined_until' => now()->addDay(),
        ]);

        $this->getJson('/api/v1/quarantine', $this->auth(['quarantine.manage']))
            ->assertOk()
            ->assertJsonStructure(['data', 'current_page']);

        Queue::fake();

        $this->postJson('/api/v1/quarantine/'.$releaseLead->uuid.'/release', [], $this->auth(['quarantine.manage']))
            ->assertOk()
            ->assertJsonPath('status', 'queued');

        $this->postJson('/api/v1/quarantine/'.$rejectLead->uuid.'/reject', [], $this->auth(['quarantine.manage']))
            ->assertOk()
            ->assertJsonPath('status', 'rejected');

        $this->assertSame(LeadStatus::Rejected, $rejectLead->fresh()->status);
    }

    public function test_buyer_feedback_and_credit_api(): void
    {
        $soldLead = Lead::where('account_id', $this->account->id)
            ->where('status', 'sold')
            ->where('sold_to_buyer_id', $this->buyer->id)
            ->first();

        if (! $soldLead) {
            $soldLead = Lead::where('account_id', $this->account->id)->where('status', 'sold')->first();
            $soldLead->update(['sold_to_buyer_id' => $this->buyer->id]);
        }

        $before = (float) $this->buyer->fresh()->credit_balance;

        $this->postJson('/api/v1/buyers/'.$this->buyer->id.'/credit', [
            'amount' => 10,
        ], $this->auth(['buyers.manage']))
            ->assertOk()
            ->assertJsonStructure(['credit_balance', 'transaction_id']);

        $this->assertEquals($before + 10, (float) $this->buyer->fresh()->credit_balance);

        $this->postJson('/api/v1/buyers/'.$this->buyer->id.'/feedback', [
            'lead_uuid' => $soldLead->uuid,
            'status' => 'contacted',
            'converted' => false,
            'notes' => 'API feedback test',
        ], $this->auth(['buyers.manage']))
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_mock_buyer_and_dev_ping_post_endpoints(): void
    {
        $this->getJson('/api/v1/mock/buyers')->assertOk()->assertJsonStructure(['tiers']);

        $this->postJson('/api/v1/mock/buyers/1/ping', ['floor' => 10])
            ->assertOk()
            ->assertJsonPath('Success', true);

        $this->postJson('/api/v1/ping', ['floor' => 12, 'bid_hint' => 18])
            ->assertOk()
            ->assertJsonStructure(['Success', 'Cost', 'PingID']);

        $this->postJson('/api/v1/post')->assertOk()->assertJsonPath('Success', true);
    }

    public function test_integration_webhooks(): void
    {
        $this->postJson('/api/v1/integrations/stripe/webhook', ['type' => 'test'])
            ->assertStatus(403);

        $this->account->update([
            'settings' => array_merge($this->account->settings ?? [], [
                'stripe' => ['enabled' => true],
            ]),
        ]);

        $this->postJson('/api/v1/integrations/stripe/webhook', ['type' => 'test'])
            ->assertOk()
            ->assertJsonPath('received', true);

        $this->getJson('/api/v1/integrations/google/webhook/'.$this->account->slug)
            ->assertOk()
            ->assertJsonPath('provider', 'google');

        $this->postJson('/api/v1/integrations/google/ingest/'.$this->account->slug, ['email' => 'x@y.com'])
            ->assertStatus(403);

        $settings = $this->account->settings ?? [];
        $settings['lead_sources']['google'] = ['enabled' => true, 'campaign_id' => $this->campaign->id];
        $this->account->update(['settings' => $settings]);

        $this->postJson('/api/v1/integrations/google/ingest/'.$this->account->slug, ['email' => 'x@y.com'])
            ->assertStatus(202)
            ->assertJsonPath('accepted', true);
    }

    public function test_api_auth_and_permission_boundaries(): void
    {
        $this->postJson('/api/v1/leads', [])->assertUnauthorized();

        $this->postJson('/api/v1/leads', [
            'campaign_reference' => $this->campaign->reference,
        ], ['Authorization' => 'Bearer not-a-valid-key'])
            ->assertUnauthorized();

        $readOnly = $this->auth(['leads.read']);
        $this->postJson('/api/v1/leads', [
            'campaign_reference' => $this->campaign->reference,
            'email' => 'x@y.com',
        ], $readOnly)->assertForbidden();

        $createOnly = $this->auth(['leads.create']);
        $lead = Lead::where('account_id', $this->account->id)->first();
        $this->getJson('/api/v1/leads/'.$lead->uuid, $createOnly)->assertForbidden();

        $this->getJson('/api/v1/reports/leads', $createOnly)->assertForbidden();

        app(AccountBillingService::class)->lock($this->account->fresh(), 'API lock test');

        $this->postJson('/api/v1/leads', [
            'campaign_reference' => $this->campaign->reference,
            'sync' => true,
            'firstname' => 'Locked',
            'lastname' => 'Api',
            'email' => 'locked.api.'.uniqid().'@example.com',
            'phone1' => '07700900777',
            'zipcode' => 'SW1A 1AA',
        ], $this->auth(['leads.create']))
            ->assertStatus(402);
    }

    public function test_cross_tenant_campaign_access_denied(): void
    {
        $other = Account::where('slug', 'insurance-ca')->first();
        $otherCampaign = Campaign::where('account_id', $other->id)->first();

        $this->postJson('/api/v1/leads', [
            'campaign_reference' => $otherCampaign->reference,
            'sync' => true,
            'firstname' => 'Cross',
            'lastname' => 'Tenant',
            'email' => 'cross.'.uniqid().'@example.com',
            'phone1' => '07700900888',
            'zipcode' => 'SW1A 1AA',
        ], $this->auth(['leads.create']))
            ->assertNotFound();
    }

    public function test_unknown_lead_and_quarantine_return_404(): void
    {
        $this->getJson('/api/v1/leads/'.Str::uuid(), $this->auth(['leads.read']))
            ->assertNotFound();

        $this->postJson('/api/v1/quarantine/'.Str::uuid().'/release', [], $this->auth(['quarantine.manage']))
            ->assertNotFound();
    }
}
