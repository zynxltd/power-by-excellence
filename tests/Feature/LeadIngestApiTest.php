<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Jobs\ProcessLeadJob;
use App\Models\Account;
use App\Models\Campaign;
use App\Models\CampaignField;
use App\Models\Delivery;
use App\Enums\DeliveryMethod;
use App\Models\Buyer;
use App\Models\Lead;
use App\Services\Api\ApiKeyService;
use App\Services\Leads\LeadPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class LeadIngestApiTest extends TestCase
{
    use RefreshDatabase;

    protected Account $account;

    protected Campaign $campaign;

    protected string $apiToken;

    protected function setUp(): void
    {
        parent::setUp();

        $this->account = Account::create([
            'name' => 'Test Platform',
            'slug' => 'test-platform',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
        ]);

        $this->campaign = Campaign::create([
            'account_id' => $this->account->id,
            'name' => 'Test Campaign',
            'reference' => 'test-campaign',
            'payout_amount' => 5,
            'floor_price' => 10,
        ]);

        foreach (['firstname', 'lastname', 'email', 'phone1', 'zipcode'] as $i => $name) {
            CampaignField::create([
                'campaign_id' => $this->campaign->id,
                'name' => $name,
                'required' => true,
                'sort_order' => $i,
            ]);
        }

        $buyer = Buyer::create([
            'account_id' => $this->account->id,
            'reference' => 'test-buyer',
            'name' => 'Test Buyer',
        ]);

        Delivery::create([
            'campaign_id' => $this->campaign->id,
            'buyer_id' => $buyer->id,
            'name' => 'Store',
            'method' => DeliveryMethod::StoreLead,
            'status' => 'active',
            'priority' => 1,
            'revenue_amount' => 20,
        ]);

        $key = app(ApiKeyService::class)->create([
            'account_id' => $this->account->id,
            'name' => 'Test Key',
            'type' => 'administrator',
            'permissions' => ['leads.create', 'leads.read'],
        ]);

        $this->apiToken = $key['token'];
    }

    public function test_lead_ingest_queues_processing(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/leads', [
            'campaign_reference' => 'test-campaign',
            'firstname' => 'Jane',
            'lastname' => 'Doe',
            'email' => 'jane@example.com',
            'phone1' => '07700900123',
            'zipcode' => 'SW1A 1AA',
        ], ['Authorization' => 'Bearer '.$this->apiToken]);

        $response->assertStatus(202)
            ->assertJsonStructure(['status', 'queue_id', 'lead_id']);

        Queue::assertPushed(ProcessLeadJob::class);
    }

    public function test_sync_lead_ingest_sells_lead(): void
    {
        $response = $this->postJson('/api/v1/leads', [
            'campaign_reference' => 'test-campaign',
            'sync' => true,
            'firstname' => 'John',
            'lastname' => 'Smith',
            'email' => 'john@example.com',
            'phone1' => '07700900456',
            'zipcode' => 'EC1A 1BB',
        ], ['Authorization' => 'Bearer '.$this->apiToken]);

        $response->assertOk()
            ->assertJsonPath('status', LeadStatus::Sold->value);

        $this->assertDatabaseHas('lead_financials', ['revenue' => 20]);
    }

    public function test_test_mode_skips_live_delivery(): void
    {
        $response = $this->postJson('/api/v1/leads', [
            'campaign_reference' => 'test-campaign',
            'sync' => true,
            'test' => true,
            'firstname' => 'Test',
            'lastname' => 'Mode',
            'email' => 'testmode.'.uniqid().'@example.com',
            'phone1' => '07700900888',
            'zipcode' => 'EC1A 1BB',
        ], ['Authorization' => 'Bearer '.$this->apiToken]);

        $response->assertOk()
            ->assertJsonPath('status', LeadStatus::Accepted->value)
            ->assertJsonPath('test_mode', true)
            ->assertJsonPath('buyer_reference', null);

        $lead = Lead::where('uuid', $response->json('lead_id'))->first();
        $this->assertNotNull($lead);
        $this->assertDatabaseMissing('lead_financials', ['lead_id' => $lead->id]);
        $this->assertSame(0, \App\Models\DeliveryLog::where('lead_id', $lead->id)->count());
    }

    public function test_duplicate_lead_rejected(): void
    {
        $payload = [
            'campaign_reference' => 'test-campaign',
            'sync' => true,
            'firstname' => 'Dup',
            'lastname' => 'Test',
            'email' => 'dup@example.com',
            'phone1' => '07700900789',
            'zipcode' => 'W1A 0AX',
        ];

        $this->postJson('/api/v1/leads', $payload, ['Authorization' => 'Bearer '.$this->apiToken]);
        $response = $this->postJson('/api/v1/leads', $payload, ['Authorization' => 'Bearer '.$this->apiToken]);

        $response->assertOk()->assertJsonPath('status', LeadStatus::Duplicate->value);
    }

    public function test_invalid_api_key_rejected(): void
    {
        $response = $this->postJson('/api/v1/leads', [
            'campaign_reference' => 'test-campaign',
            'email' => 'x@y.com',
        ], ['Authorization' => 'Bearer invalid|key']);

        $response->assertUnauthorized();
    }

    public function test_multi_tenant_isolation(): void
    {
        $otherAccount = Account::create(['name' => 'Other', 'slug' => 'other', 'default_currency' => 'GBP', 'default_country' => 'GB']);
        Campaign::create(['account_id' => $otherAccount->id, 'name' => 'Secret', 'reference' => 'secret-campaign']);

        $response = $this->postJson('/api/v1/leads', [
            'campaign_reference' => 'secret-campaign',
            'firstname' => 'Hack',
            'lastname' => 'Attempt',
            'email' => 'hack@example.com',
            'phone1' => '07700900000',
            'zipcode' => 'AB1 2CD',
        ], ['Authorization' => 'Bearer '.$this->apiToken]);

        $response->assertNotFound();
    }
}
