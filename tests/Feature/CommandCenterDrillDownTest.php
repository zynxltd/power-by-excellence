<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommandCenterDrillDownTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
    }

    public function test_super_admin_can_drill_to_delivery_logs_from_command_center_on_central_host(): void
    {
        $super = User::where('email', 'admin@powerbyexcellence.test')->firstOrFail();
        $ca = Account::where('slug', 'insurance-ca')->firstOrFail();

        $this->actingAs($super)
            ->get('/logs/delivery?days=1&account_id='.$ca->id.'&has_ping=1')
            ->assertOk();
    }

    public function test_super_admin_in_god_mode_on_tenant_can_access_delivery_logs(): void
    {
        $super = User::where('email', 'admin@powerbyexcellence.test')->firstOrFail();
        $ca = Account::where('slug', 'insurance-ca')->firstOrFail();

        $this->withServerVariables(['HTTP_HOST' => 'insurance-ca.powerbyexcellence.test'])
            ->actingAs($super)
            ->withSession(['god_mode' => true, 'current_account_id' => $ca->id])
            ->get('http://insurance-ca.powerbyexcellence.test/logs/delivery')
            ->assertOk();
    }

    public function test_supplier_portal_user_cannot_access_delivery_logs(): void
    {
        $supplier = User::where('email', 'supplier-portal@insurance-ca.test')->firstOrFail();

        $this->withServerVariables(['HTTP_HOST' => 'insurance-ca.powerbyexcellence.test'])
            ->actingAs($supplier)
            ->get('http://insurance-ca.powerbyexcellence.test/logs/delivery')
            ->assertRedirect(route('portal.supplier.dashboard', absolute: false));
    }
}
