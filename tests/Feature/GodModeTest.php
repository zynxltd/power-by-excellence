<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GodModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
    }

    public function test_super_admin_can_visit_tenant_via_god_mode_handoff(): void
    {
        $super = User::where('email', 'admin@powerbyexcellence.test')->first();
        $account = Account::where('slug', 'excellence-uk')->first();

        $this->assertNotNull(\App\Support\Tenancy\TenantResolver::resolveFromHost('excellence-uk.powerbyexcellence.test'));

        $token = 'test-god-mode-token';
        Cache::put("god_mode_handoff:{$token}", [
            'super_admin_id' => $super->id,
            'account_id' => $account->id,
        ], now()->addMinutes(2));

        $this->assertNotNull(Cache::get("god_mode_handoff:{$token}"));

        $response = $this->get('http://excellence-uk.powerbyexcellence.test/god-mode/handoff/'.$token);

        if ($response->status() !== 302) {
            $this->fail('Handoff status '.$response->status().': '.($response->exception?->getMessage() ?? ''));
        }

        $this->assertTrue(session('god_mode'));
        $this->assertSame($account->id, session('current_account_id'));
    }

    public function test_super_admin_visit_redirects_to_handoff(): void
    {
        $super = User::where('email', 'admin@powerbyexcellence.test')->first();
        $account = Account::where('slug', 'excellence-uk')->first();

        $this->actingAs($super)
            ->post(route('accounts.visit', ['accountId' => $account->id], absolute: false))
            ->assertRedirect()
            ->assertSessionHas('current_account_id', $account->id);
    }

    public function test_super_admin_billing_works_with_switched_tenant(): void
    {
        $super = User::where('email', 'admin@powerbyexcellence.test')->first();
        $account = Account::where('slug', 'excellence-uk')->first();

        $this->actingAs($super)
            ->withSession(['current_account_id' => $account->id])
            ->get('/billing')
            ->assertOk();
    }
}
