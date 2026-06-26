<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\User;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BrandingFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    protected User $ukAdmin;

    protected User $superAdmin;

    protected Account $ukAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->ukAdmin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->superAdmin = User::where('email', 'admin@powerbyexcellence.test')->first();
        $this->ukAccount = Account::where('slug', 'excellence-uk')->first();
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_branding_routes_map_to_settings_module(): void
    {
        $this->assertSame('settings', AdminModules::moduleForRoute('branding.edit'));
        $this->assertSame('settings', AdminModules::moduleForRoute('branding.update'));
    }

    public function test_branding_edit_loads_account_fields(): void
    {
        $this->ukAccount->update([
            'name' => 'Excellence UK Internal',
            'brand_name' => 'Excellence Leads UK',
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('branding.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Branding/Edit')
                ->where('account.id', $this->ukAccount->id)
                ->where('account.name', 'Excellence UK Internal')
                ->where('account.brand_name', 'Excellence Leads UK')
            );
    }

    public function test_tenant_admin_can_update_branding_with_assets(): void
    {
        Storage::fake('public');

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('branding.update'), [
                'name' => 'Excellence UK Platform',
                'brand_name' => 'Excellence Leads',
                'logo' => UploadedFile::fake()->image('logo.png', 200, 48),
                'favicon' => UploadedFile::fake()->image('favicon.png', 32, 32),
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Branding updated.');

        $account = $this->ukAccount->fresh();
        $this->assertSame('Excellence UK Platform', $account->name);
        $this->assertSame('Excellence Leads', $account->brand_name);
        $this->assertNotNull($account->logo_path);
        $this->assertNotNull($account->favicon_path);
        Storage::disk('public')->assertExists($account->logo_path);
        Storage::disk('public')->assertExists($account->favicon_path);
    }

    public function test_clearing_public_name_uses_internal_name_for_display(): void
    {
        $this->ukAccount->update([
            'name' => 'Excellence UK Internal',
            'brand_name' => 'Old Public Name',
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('branding.update'), [
                'name' => 'Excellence UK Internal',
                'brand_name' => '',
            ])
            ->assertRedirect();

        $this->ukAccount->refresh();
        $this->assertNull($this->ukAccount->brand_name);

        $this->actingAs($this->ukAdmin)
            ->get('http://excellence-uk.powerbyexcellence.test/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.account.display_name', 'Excellence UK Internal')
                ->where('tenant.display_name', 'Excellence UK Internal')
            );
    }

    public function test_remove_logo_and_favicon(): void
    {
        Storage::fake('public');

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('branding.update'), [
                'name' => $this->ukAccount->name,
                'brand_name' => 'Branded',
                'logo' => UploadedFile::fake()->image('logo.png'),
                'favicon' => UploadedFile::fake()->image('favicon.png'),
            ]);

        $account = $this->ukAccount->fresh();
        $logoPath = $account->logo_path;
        $faviconPath = $account->favicon_path;
        $this->assertNotNull($logoPath);
        $this->assertNotNull($faviconPath);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('branding.update'), [
                'name' => $this->ukAccount->name,
                'brand_name' => 'Branded',
                'remove_logo' => true,
                'remove_favicon' => true,
            ])
            ->assertRedirect();

        $account->refresh();
        $this->assertNull($account->logo_path);
        $this->assertNull($account->favicon_path);
        Storage::disk('public')->assertMissing($logoPath);
        Storage::disk('public')->assertMissing($faviconPath);
    }

    public function test_branding_update_only_affects_current_tenant(): void
    {
        $otherAccount = Account::where('slug', 'insurance-ca')->first();
        $originalName = $otherAccount->name;

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('branding.update'), [
                'name' => 'UK Only Branding',
                'brand_name' => 'UK White Label',
            ])
            ->assertRedirect();

        $this->assertSame('UK Only Branding', $this->ukAccount->fresh()->name);
        $this->assertSame($originalName, $otherAccount->fresh()->name);
    }

    public function test_super_admin_without_tenant_redirected_from_branding(): void
    {
        $this->actingAs($this->superAdmin)
            ->get(route('branding.edit'))
            ->assertRedirect(route('accounts.index'));
    }

    public function test_super_admin_with_switched_tenant_can_update_branding(): void
    {
        $us = Account::where('slug', 'partner-solar-us')->first();

        $this->actingAs($this->superAdmin)
            ->withSession(['current_account_id' => $us->id])
            ->post(route('branding.update'), [
                'name' => 'Solar US Platform',
                'brand_name' => 'Solar Leads US',
            ])
            ->assertRedirect();

        $us->refresh();
        $this->assertSame('Solar US Platform', $us->name);
        $this->assertSame('Solar Leads US', $us->brand_name);
        $this->assertNotSame('Solar US Platform', $this->ukAccount->fresh()->name);
    }

    public function test_login_page_shows_tenant_branding_on_partner_domain(): void
    {
        $this->ukAccount->update([
            'name' => 'Excellence UK',
            'brand_name' => 'Excellence Leads UK',
        ]);

        $this->get('http://excellence-uk.powerbyexcellence.test/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Auth/Login')
                ->where('tenant.display_name', 'Excellence Leads UK')
                ->where('tenant.name', 'Excellence Leads UK')
            );
    }

    public function test_partner_domain_shares_tenant_branding_in_inertia(): void
    {
        Storage::fake('public');

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('branding.update'), [
                'name' => 'Excellence UK',
                'brand_name' => 'Excellence Leads',
                'logo' => UploadedFile::fake()->image('logo.png', 200, 48),
                'favicon' => UploadedFile::fake()->image('favicon.png', 32, 32),
            ]);

        $account = $this->ukAccount->fresh();

        $this->actingAs($this->ukAdmin)
            ->get('http://excellence-uk.powerbyexcellence.test/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('tenant.display_name', 'Excellence Leads')
                ->where('tenant.logo_url', fn ($url) => is_string($url) && str_contains($url, $account->logo_path))
                ->where('faviconUrl', fn ($url) => is_string($url) && str_contains($url, $account->favicon_path))
            );
    }

    public function test_staff_without_settings_module_cannot_access_branding(): void
    {
        $staff = User::factory()->create([
            'account_id' => $this->ukAccount->id,
            'role' => UserRole::Staff,
            'allowed_modules' => ['reports'],
        ]);

        $this->ukHost()
            ->actingAs($staff)
            ->get(route('branding.edit'))
            ->assertForbidden();
    }

    public function test_account_public_branding_helper_matches_display_rules(): void
    {
        $this->ukAccount->update([
            'name' => 'Internal Platform Name',
            'brand_name' => null,
        ]);

        $branding = $this->ukAccount->fresh()->publicBranding();
        $this->assertSame('Internal Platform Name', $branding['display_name']);
        $this->assertSame('Internal Platform Name', $branding['name']);
        $this->assertNull($branding['logo_url']);
        $this->assertNull($branding['favicon_url']);

        $this->ukAccount->update(['brand_name' => 'Public Brand']);
        $branding = $this->ukAccount->fresh()->publicBranding();
        $this->assertSame('Public Brand', $branding['display_name']);
    }
}
