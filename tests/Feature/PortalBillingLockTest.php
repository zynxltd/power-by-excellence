<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalBillingLockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
    }

    public function test_locked_account_redirects_buyer_portal_to_lock_page(): void
    {
        $account = Account::where('slug', 'excellence-uk')->first();
        app(AccountBillingService::class)->lock($account, 'Payment overdue');

        $buyer = Buyer::where('reference', 'buyer-primary')->first();
        $buyerUser = User::where('buyer_id', $buyer->id)->where('role', UserRole::BuyerPortal)->first();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($buyerUser)
            ->get('/portal/buyer')
            ->assertRedirect(route('portal.billing.lock'));

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($buyerUser)
            ->get('/portal/billing/lock')
            ->assertOk();
    }

    public function test_locked_account_allows_buyer_billing_route(): void
    {
        $account = Account::where('slug', 'excellence-uk')->first();
        app(AccountBillingService::class)->lock($account, 'Payment overdue');

        $buyer = Buyer::where('reference', 'buyer-primary')->first();
        $buyerUser = User::where('buyer_id', $buyer->id)->where('role', UserRole::BuyerPortal)->first();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($buyerUser)
            ->get('/portal/buyer/billing')
            ->assertOk();
    }
}
