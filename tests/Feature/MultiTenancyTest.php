<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\User;
use App\Enums\UserRole;
use App\Support\Tenancy\AccountContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_scoped_queries(): void
    {
        $accountA = Account::create(['name' => 'A', 'slug' => 'a', 'default_currency' => 'GBP', 'default_country' => 'GB']);
        $accountB = Account::create(['name' => 'B', 'slug' => 'b', 'default_currency' => 'GBP', 'default_country' => 'GB']);

        Campaign::create(['account_id' => $accountA->id, 'name' => 'Camp A', 'reference' => 'a']);
        Campaign::create(['account_id' => $accountB->id, 'name' => 'Camp B', 'reference' => 'b']);

        AccountContext::set($accountA);

        $this->assertCount(1, Campaign::all());
        $this->assertEquals('Camp A', Campaign::first()->name);

        AccountContext::clear();
    }

    public function test_super_admin_can_access_dashboard(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertOk();
    }
}
