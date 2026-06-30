<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Services\Billing\BuyerBillingService;
use App\Services\Leads\LeadPipeline;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountBillingLockTest extends TestCase
{
    use RefreshDatabase;

    protected function seedMinimalPlatform(): array
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);

        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $campaign = Campaign::where('reference', 'auto-insurance-uk')->first();
        $buyer = Buyer::where('reference', 'buyer-primary')->first();

        return compact('admin', 'campaign', 'buyer');
    }

    public function test_locked_account_redirects_admin_from_dashboard(): void
    {
        $this->withoutVite();
        ['admin' => $admin] = $this->seedMinimalPlatform();

        $account = $admin->account;
        app(AccountBillingService::class)->lock($account, 'Payment overdue');

        $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('billing.lock'));
        $this->actingAs($admin)->get('/billing')->assertOk();
        $this->actingAs($admin)->get('/billing/lock')->assertOk();
    }

    public function test_past_due_account_continues_lead_processing(): void
    {
        ['campaign' => $campaign] = $this->seedMinimalPlatform();

        $account = $campaign->account;
        $settings = $account->settings ?? [];
        $settings['billing_status'] = AccountBillingService::STATUS_PAST_DUE;
        $account->update(['settings' => $settings]);

        $lead = Lead::create([
            'account_id' => $account->id,
            'campaign_id' => $campaign->id,
            'status' => 'pending',
            'field_data' => ['email' => 'test@example.com', 'phone1' => '07700900000', 'zipcode' => 'SW1A 1AA', 'lastname' => 'Test'],
            'received_at' => now(),
        ]);

        $processed = app(LeadPipeline::class)->process($lead->fresh());

        $this->assertNotEquals('rejected', $processed->fresh()->status->value);
    }

    public function test_locked_account_rejects_lead_processing(): void
    {
        ['campaign' => $campaign] = $this->seedMinimalPlatform();

        app(AccountBillingService::class)->lock($campaign->account, 'Payment overdue');

        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => 'pending',
            'field_data' => ['email' => 'test@example.com', 'phone1' => '07700900000'],
            'received_at' => now(),
        ]);

        $processed = app(LeadPipeline::class)->process($lead->fresh());

        $this->assertEquals('rejected', $processed->fresh()->status->value);
    }

    public function test_inactive_buyer_cannot_receive_credit_charges(): void
    {
        $account = Account::create([
            'name' => 'Test', 'slug' => 'test', 'default_currency' => 'GBP', 'default_country' => 'GB',
            'settings' => ['require_buyer_prepay' => true],
        ]);

        $buyer = Buyer::create([
            'account_id' => $account->id,
            'reference' => 'b1',
            'name' => 'Buyer',
            'credit_balance' => 100,
            'status' => 'inactive',
        ]);

        $billing = app(BuyerBillingService::class);
        $this->assertFalse($billing->hasCredit($buyer, 10));
        $this->assertNull($billing->charge($buyer, 10));
    }

    public function test_unlock_restores_dashboard_access(): void
    {
        $this->withoutVite();
        ['admin' => $admin] = $this->seedMinimalPlatform();

        $billing = app(AccountBillingService::class);
        $billing->lock($admin->account, 'Test lock');

        $this->actingAs($admin)->get('/dashboard')->assertRedirect(route('billing.lock'));

        $billing->unlock($admin->account->fresh());

        $this->actingAs($admin)->get('/dashboard')->assertOk();
    }

    public function test_integrations_and_buyer_show_pages_load(): void
    {
        $this->withoutVite();
        ['admin' => $admin, 'buyer' => $buyer] = $this->seedMinimalPlatform();

        $this->actingAs($admin)->get('/integrations')->assertOk();
        $this->actingAs($admin)->get("/buyers/{$buyer->id}")->assertOk();
        $supplier = \App\Models\Supplier::where('reference', 'supplier-main')->first();
        $this->actingAs($admin)->get("/suppliers/{$supplier->id}")->assertOk();
    }
}
