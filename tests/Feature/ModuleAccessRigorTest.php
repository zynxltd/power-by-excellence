<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleAccessRigorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
    }

    public function test_staff_without_reports_module_is_blocked_from_reports(): void
    {
        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $staff = User::factory()->create([
            'account_id' => $admin->account_id,
            'role' => UserRole::Staff,
            'allowed_modules' => ['dashboard', 'operations', 'campaigns'],
        ]);

        $host = $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);

        $host->actingAs($staff)->get(route('reports.index'))->assertForbidden();
        $host->actingAs($staff)->get(route('operations.index'))->assertOk();
        $host->actingAs($staff)->get(route('campaigns.index'))->assertOk();
    }

    public function test_staff_with_reports_module_can_access_reports(): void
    {
        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $staff = User::factory()->create([
            'account_id' => $admin->account_id,
            'role' => UserRole::Staff,
            'allowed_modules' => ['dashboard', 'reports'],
        ]);

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($staff)
            ->get(route('reports.index'))
            ->assertOk();
    }

    public function test_reports_module_maps_correctly_in_admin_modules(): void
    {
        $this->assertSame('reports', \App\Support\AdminModules::moduleForRoute('reports.index'));
        $this->assertContains('reports', \App\Support\AdminModules::defaultsForStaff());
    }

    public function test_account_admin_has_all_modules(): void
    {
        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();

        foreach (\App\Support\AdminModules::keys() as $module) {
            $this->assertTrue($admin->hasModuleAccess($module), "Account admin missing module: {$module}");
        }
    }
}
