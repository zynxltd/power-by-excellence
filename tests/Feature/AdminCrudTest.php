<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->account = $this->admin->account;
    }

    public function test_campaign_crud(): void
    {
        $this->withoutVite();
        $this->actingAs($this->admin);

        $this->get(route('campaigns.create'))->assertOk();

        $response = $this->post(route('campaigns.store'), [
            'name' => 'Test Campaign',
            'reference' => 'test-crud-campaign',
            'type' => 'standard',
            'country' => 'US',
            'currency' => 'USD',
            'status' => 'active',
            'payout_amount' => 5,
            'floor_price' => 10,
            'sell_mode' => 'exclusive',
            'use_advanced_distribution' => false,
        ]);

        $campaign = Campaign::where('reference', 'test-crud-campaign')->first();
        $this->assertNotNull($campaign);
        $response->assertRedirect(route('campaigns.show', $campaign));

        $this->put(route('campaigns.update', $campaign), [
            'name' => 'Updated Campaign',
            'reference' => 'test-crud-campaign',
            'country' => 'US',
            'currency' => 'USD',
            'status' => 'active',
            'payout_amount' => 8,
            'floor_price' => 15,
            'sell_mode' => 'exclusive',
            'use_advanced_distribution' => true,
        ])->assertRedirect(route('campaigns.show', $campaign));

        $campaign->refresh();
        $this->assertSame('Updated Campaign', $campaign->name);
        $this->assertSame('US', $campaign->country);

        $this->delete(route('campaigns.destroy', $campaign))->assertRedirect(route('campaigns.index'));
        $this->assertNull($campaign->fresh());
    }

    public function test_buyer_crud(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('buyers.store'), [
            'reference' => 'crud-buyer',
            'name' => 'CRUD Buyer',
            'email' => 'crud@buyer.test',
            'status' => 'active',
            'credit_balance' => 500,
        ]);

        $buyer = Buyer::where('reference', 'crud-buyer')->first();
        $this->assertNotNull($buyer);
        $response->assertRedirect(route('buyers.show', $buyer));

        $this->put(route('buyers.update', $buyer), [
            'reference' => 'crud-buyer',
            'name' => 'Updated Buyer',
            'email' => 'updated@buyer.test',
            'status' => 'active',
            'credit_balance' => 750,
        ])->assertRedirect(route('buyers.show', $buyer));

        $buyer->refresh();
        $this->assertSame('Updated Buyer', $buyer->name);
        $this->assertEquals(750, (float) $buyer->credit_balance);

        $this->delete(route('buyers.destroy', $buyer))->assertRedirect(route('buyers.index'));
        $this->assertNull($buyer->fresh());
    }

    public function test_supplier_crud(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post(route('suppliers.store'), [
            'reference' => 'crud-supplier',
            'name' => 'CRUD Supplier',
            'status' => 'active',
            'sources' => [
                ['sid' => 'crud_sid', 'name' => 'CRUD Source'],
            ],
        ]);

        $supplier = Supplier::where('reference', 'crud-supplier')->first();
        $this->assertNotNull($supplier);
        $this->assertCount(1, $supplier->sources);
        $response->assertRedirect(route('suppliers.show', $supplier));

        $this->put(route('suppliers.update', $supplier), [
            'reference' => 'crud-supplier',
            'name' => 'Updated Supplier',
            'status' => 'active',
            'sources' => [
                ['sid' => 'crud_sid', 'name' => 'CRUD Source Updated'],
            ],
        ])->assertRedirect(route('suppliers.show', $supplier));

        $supplier->refresh();
        $this->assertSame('Updated Supplier', $supplier->name);

        $this->delete(route('suppliers.destroy', $supplier))->assertRedirect(route('suppliers.index'));
        $this->assertNull($supplier->fresh());
    }

    public function test_delivery_crud(): void
    {
        $this->actingAs($this->admin);

        $campaign = Campaign::first();
        $buyer = Buyer::first();

        $this->post(route('deliveries.store'), [
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => 'CRUD Delivery',
            'method' => 'direct_post',
            'trigger_type' => 'on_lead_arrival',
            'status' => 'active',
            'priority' => 50,
            'weight' => 100,
            'tier' => 1,
            'revenue_type' => 'fixed',
            'revenue_amount' => 20,
            'config' => ['url' => 'https://example.com/post'],
        ])->assertRedirect(route('deliveries.index'));

        $delivery = Delivery::where('name', 'CRUD Delivery')->first();
        $this->assertNotNull($delivery);

        $this->put(route('deliveries.update', $delivery), [
            'campaign_id' => $campaign->id,
            'buyer_id' => $buyer->id,
            'name' => 'Updated Delivery',
            'method' => 'direct_post',
            'trigger_type' => 'on_lead_arrival',
            'status' => 'active',
            'priority' => 60,
            'weight' => 100,
            'tier' => 1,
            'revenue_type' => 'fixed',
            'revenue_amount' => 25,
            'config' => ['url' => 'https://example.com/post'],
        ])->assertRedirect(route('deliveries.index'));

        $delivery->refresh();
        $this->assertSame('Updated Delivery', $delivery->name);

        $this->delete(route('deliveries.destroy', $delivery))->assertRedirect(route('deliveries.index'));
        $this->assertNull($delivery->fresh());
    }

    public function test_webhook_and_api_key_crud(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('webhooks.store'), [
            'name' => 'Test Hook',
            'url' => 'https://example.com/webhook',
            'events' => ['lead.sold'],
            'is_active' => true,
        ])->assertRedirect();

        $webhook = \App\Models\Webhook::where('name', 'Test Hook')->first();
        $this->assertNotNull($webhook);

        $this->delete(route('webhooks.destroy', $webhook))->assertRedirect();
        $this->assertNull($webhook->fresh());

        $this->post(route('api-keys.store'), [
            'name' => 'CRUD Key',
            'type' => 'administrator',
        ])->assertRedirect();

        $key = \App\Models\ApiKey::where('name', 'CRUD Key')->first();
        $this->assertNotNull($key);

        $this->delete(route('api-keys.destroy', $key))->assertRedirect();
        $this->assertNull($key->fresh());
    }

    public function test_user_crud(): void
    {
        $this->actingAs($this->admin);

        $this->post(route('users.store'), [
            'name' => 'CRUD User',
            'email' => 'crud-user@test.com',
            'password' => 'password',
            'role' => 'staff',
        ])->assertRedirect();

        $user = User::where('email', 'crud-user@test.com')->first();
        $this->assertNotNull($user);

        $this->delete(route('users.destroy', $user))->assertRedirect();
        $this->assertNull($user->fresh());
    }

    public function test_branding_update(): void
    {
        Storage::fake('public');
        $this->withoutVite();
        $this->actingAs($this->admin);

        $this->get(route('branding.edit'))->assertOk();

        $this->post(route('branding.update'), [
            'name' => 'Branded Platform',
            'brand_name' => 'My White Label',
            'logo' => UploadedFile::fake()->image('logo.png', 200, 48),
            'favicon' => UploadedFile::fake()->image('favicon.png', 32, 32),
        ])->assertRedirect();

        $this->account->refresh();
        $this->assertSame('Branded Platform', $this->account->name);
        $this->assertSame('My White Label', $this->account->brand_name);
        $this->assertNotNull($this->account->logo_path);
        $this->assertNotNull($this->account->favicon_path);
    }

    public function test_lead_filters(): void
    {
        $this->withoutVite();
        $this->actingAs($this->admin);

        $this->get(route('leads.index', ['status' => 'sold']))->assertOk();
        $this->get(route('leads.index', ['search' => 'abc']))->assertOk();
    }
}
