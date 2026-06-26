<?php

namespace Tests\Feature;

use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlashToastTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_billing_top_up_sets_session_success_for_toast(): void
    {
        $buyer = Buyer::whereHas('account', fn ($q) => $q->where('slug', 'excellence-uk'))->first();

        $this->ukHost()
            ->actingAs($this->admin)
            ->post(route('billing.top-up', $buyer), [
                'amount' => 15,
                'type' => 'chargeback',
                'description' => 'Test chargeback toast',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Ledger entry recorded.');
    }

    public function test_campaign_crud_actions_flash_success(): void
    {
        $this->ukHost()->actingAs($this->admin);

        $this->post(route('campaigns.store'), [
            'name' => 'Toast Campaign',
            'reference' => 'toast-campaign',
            'type' => 'standard',
            'country' => 'GB',
            'currency' => 'GBP',
            'status' => 'active',
            'payout_amount' => 5,
            'floor_price' => 10,
            'sell_mode' => 'exclusive',
            'use_advanced_distribution' => false,
        ])->assertSessionHas('success', 'Campaign created.');

        $campaign = Campaign::where('reference', 'toast-campaign')->firstOrFail();

        $this->put(route('campaigns.update', $campaign), [
            'name' => 'Toast Campaign Updated',
            'reference' => 'toast-campaign',
            'country' => 'GB',
            'currency' => 'GBP',
            'status' => 'active',
            'payout_amount' => 6,
            'floor_price' => 11,
            'sell_mode' => 'exclusive',
            'use_advanced_distribution' => false,
        ])->assertSessionHas('success', 'Campaign updated.');

        $this->delete(route('campaigns.destroy', $campaign))
            ->assertSessionHas('success', 'Campaign deleted.');
    }

    public function test_webhook_and_postback_crud_flash_success(): void
    {
        $this->ukHost()->actingAs($this->admin);

        $this->post(route('webhooks.store'), [
            'name' => 'Toast Webhook',
            'url' => 'https://example.com/webhook',
            'events' => ['lead.sold'],
            'is_active' => true,
        ])->assertSessionHas('success', 'Webhook created.');

        $webhook = \App\Models\Webhook::where('name', 'Toast Webhook')->firstOrFail();

        $this->delete(route('webhooks.destroy', $webhook))
            ->assertSessionHas('success', 'Webhook deleted.');

        $this->post(route('postbacks.store'), [
            'name' => 'Toast Postback',
            'url' => 'https://example.com/postback',
            'method' => 'get',
            'events' => ['lead.sold'],
            'is_active' => true,
        ])->assertSessionHas('success', 'Postback created.');

        $postback = \App\Models\Postback::where('name', 'Toast Postback')->firstOrFail();

        $this->put(route('postbacks.update', $postback), [
            'name' => 'Toast Postback Updated',
            'url' => 'https://example.com/postback-updated',
            'method' => 'get',
            'events' => ['lead.sold'],
            'is_active' => true,
        ])->assertSessionHas('success', 'Postback updated.');

        $this->delete(route('postbacks.destroy', $postback))
            ->assertSessionHas('success', 'Postback removed.');
    }

    public function test_settings_and_demo_request_flash_success(): void
    {
        $this->withoutVite();

        $this->ukHost()
            ->actingAs($this->admin)
            ->put(route('settings.update'), [
                'name' => $this->admin->account->name,
                'timezone' => 'Europe/London',
                'default_country' => 'GB',
                'default_currency' => 'GBP',
                'supplier_iframe_embed' => true,
            ])
            ->assertSessionHas('success', 'Platform settings updated.');

        $this->post(route('demo.request'), [
            'name' => 'Jane Smith',
            'email' => 'jane@company.com',
            'company' => 'Acme Leads',
            'message' => 'Interested in solar vertical',
        ])
            ->assertSessionHas('demo_success');
    }
}
