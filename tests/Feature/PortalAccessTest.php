<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_portal_requires_buyer_role(): void
    {
        $this->withoutVite();

        $account = Account::create([
            'name' => 'T',
            'slug' => 't',
            'domain' => 't.powerbyexcellence.test',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
        ]);
        $buyer = Buyer::create(['account_id' => $account->id, 'reference' => 'b', 'name' => 'B']);

        $buyerUser = User::factory()->create([
            'account_id' => $account->id,
            'buyer_id' => $buyer->id,
            'role' => UserRole::BuyerPortal,
        ]);

        $adminUser = User::factory()->create([
            'account_id' => $account->id,
            'role' => UserRole::AccountAdmin,
        ]);

        $this->withServerVariables(['HTTP_HOST' => 't.powerbyexcellence.test'])
            ->actingAs($buyerUser)
            ->get('/portal/buyer')
            ->assertOk();

        $this->withServerVariables(['HTTP_HOST' => 't.powerbyexcellence.test'])
            ->actingAs($adminUser)
            ->get('/portal/buyer')
            ->assertForbidden();
    }
}
