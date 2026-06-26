<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\Lead;
use App\Models\Postback;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Webhook;
use App\Services\Distribution\WebhookDispatcher;
use App\Services\Integrations\BuyerWebhookSync;
use App\Services\Integrations\SupplierPostbackSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IntegrationSyncTest extends TestCase
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

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_supplier_default_postback_syncs_to_postback_manager(): void
    {
        $this->ukHost()
            ->actingAs($this->admin)
            ->post(route('suppliers.store'), [
                'reference' => 'sync-supplier',
                'name' => 'Sync Supplier',
                'status' => 'active',
                'default_postback_url' => 'https://affiliate.test/pixel?lead=[lead_uuid]',
            ])
            ->assertRedirect();

        $supplier = Supplier::where('reference', 'sync-supplier')->first();

        $postback = Postback::withoutGlobalScopes()
            ->where('supplier_id', $supplier->id)
            ->get()
            ->first(fn (Postback $p) => ($p->config['synced_from'] ?? null) === SupplierPostbackSync::SYNC_KEY);

        $this->assertNotNull($postback);
        $this->assertSame('https://affiliate.test/pixel?lead=[lead_uuid]', $postback->url);
        $this->assertSame(SupplierPostbackSync::defaultEvents(), $postback->events);
    }

    public function test_buyer_sold_webhook_syncs_to_webhook_manager(): void
    {
        $this->ukHost()
            ->actingAs($this->admin)
            ->post(route('buyers.store'), [
                'reference' => 'sync-buyer',
                'name' => 'Sync Buyer',
                'status' => 'active',
                'settings' => [
                    'sold_webhook_url' => 'https://buyer-crm.test/hooks/sold',
                ],
            ])
            ->assertRedirect();

        $buyer = Buyer::where('reference', 'sync-buyer')->first();

        $webhook = Webhook::withoutGlobalScopes()
            ->where('buyer_id', $buyer->id)
            ->get()
            ->first(fn (Webhook $w) => ($w->config['synced_from'] ?? null) === BuyerWebhookSync::SYNC_KEY);

        $this->assertNotNull($webhook);
        $this->assertSame('https://buyer-crm.test/hooks/sold', $webhook->url);
        $this->assertSame(['lead.sold'], $webhook->events);
    }

    public function test_buyer_scoped_webhook_only_fires_for_winning_buyer(): void
    {
        Http::fake();

        $buyerA = Buyer::create([
            'account_id' => $this->account->id,
            'reference' => 'buyer-a',
            'name' => 'Buyer A',
            'status' => 'active',
        ]);
        $buyerB = Buyer::create([
            'account_id' => $this->account->id,
            'reference' => 'buyer-b',
            'name' => 'Buyer B',
            'status' => 'active',
        ]);

        Webhook::create([
            'account_id' => $this->account->id,
            'buyer_id' => $buyerA->id,
            'name' => 'Buyer A sold',
            'url' => 'https://buyer-a.test/sold',
            'events' => ['lead.sold'],
            'is_active' => true,
        ]);

        Webhook::create([
            'account_id' => $this->account->id,
            'buyer_id' => $buyerB->id,
            'name' => 'Buyer B sold',
            'url' => 'https://buyer-b.test/sold',
            'events' => ['lead.sold'],
            'is_active' => true,
        ]);

        $campaign = \App\Models\Campaign::where('account_id', $this->account->id)->first();

        $lead = Lead::create([
            'account_id' => $this->account->id,
            'campaign_id' => $campaign->id,
            'sold_to_buyer_id' => $buyerA->id,
            'status' => LeadStatus::Sold,
            'field_data' => ['email' => 'sold@test.com'],
            'received_at' => now(),
        ]);

        app(WebhookDispatcher::class)->dispatch($this->account, 'lead.sold', $lead);

        Http::assertSent(fn ($request) => $request->url() === 'https://buyer-a.test/sold');
        Http::assertNotSent(fn ($request) => $request->url() === 'https://buyer-b.test/sold');
    }

    public function test_synced_webhook_cannot_be_deleted_from_manager(): void
    {
        $buyer = Buyer::create([
            'account_id' => $this->account->id,
            'reference' => 'managed-buyer',
            'name' => 'Managed Buyer',
            'status' => 'active',
        ]);

        $webhook = Webhook::create([
            'account_id' => $this->account->id,
            'buyer_id' => $buyer->id,
            'name' => 'Managed',
            'url' => 'https://buyer.test/sold',
            'events' => ['lead.sold'],
            'is_active' => true,
            'config' => ['synced_from' => BuyerWebhookSync::SYNC_KEY],
        ]);

        $this->ukHost()
            ->actingAs($this->admin)
            ->delete(route('webhooks.destroy', $webhook))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('webhooks', ['id' => $webhook->id]);
    }
}
