<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\User;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsFunctionalityTest extends TestCase
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

    public function test_settings_routes_map_to_settings_module(): void
    {
        $this->assertSame('settings', AdminModules::moduleForRoute('settings.edit'));
        $this->assertSame('settings', AdminModules::moduleForRoute('settings.update'));
    }

    public function test_settings_page_exposes_readable_billing_status(): void
    {
        $this->ukAccount->update([
            'settings' => array_merge($this->ukAccount->settings ?? [], [
                'billing_status' => 'active',
                'require_buyer_prepay' => true,
                'billing_alert_emails' => 'ops@excellence.test',
                'default_low_credit_alert' => 100,
            ]),
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Settings/Edit')
                ->where('account.id', $this->ukAccount->id)
                ->where('account.billing_status', 'active')
                ->where('account.require_buyer_prepay', true)
                ->where('account.billing_alert_emails', 'ops@excellence.test')
                ->where('account.default_low_credit_alert', 100)
                ->has('countries')
                ->has('currencies')
                ->where('countries.GB', 'United Kingdom')
                ->where('currencies', fn ($list) => collect($list)->contains('GBP') && collect($list)->contains('USD'))
            );
    }

    public function test_settings_update_only_affects_current_tenant(): void
    {
        $other = Account::where('slug', 'insurance-ca')->first();
        $otherOriginal = $other->name;

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->put(route('settings.update'), [
                'name' => 'UK Settings Only',
                'timezone' => 'Europe/London',
                'default_country' => 'GB',
                'default_currency' => 'GBP',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Platform settings updated.');

        $this->assertSame('UK Settings Only', $this->ukAccount->fresh()->name);
        $this->assertSame($otherOriginal, $other->fresh()->name);
    }

    public function test_settings_update_preserves_integration_config(): void
    {
        $this->ukAccount->update([
            'settings' => array_merge($this->ukAccount->settings ?? [], [
                'stripe' => ['enabled' => true, 'mode' => 'test'],
                'validation_integration' => ['enabled' => true, 'email_validation' => true],
            ]),
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->put(route('settings.update'), [
                'name' => 'Excellence UK',
                'timezone' => 'Europe/London',
                'default_country' => 'GB',
                'default_currency' => 'GBP',
                'require_buyer_prepay' => false,
            ])
            ->assertRedirect();

        $settings = $this->ukAccount->fresh()->settings;
        $this->assertTrue($settings['stripe']['enabled']);
        $this->assertTrue($settings['validation_integration']['email_validation']);
        $this->assertFalse($settings['require_buyer_prepay']);
    }

    public function test_unlocking_billing_status_restores_active_account(): void
    {
        $this->ukAccount->update([
            'is_active' => false,
            'settings' => array_merge($this->ukAccount->settings ?? [], [
                'billing_status' => 'locked',
                'billing_locked_at' => now()->toIso8601String(),
            ]),
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->put(route('settings.update'), [
                'name' => $this->ukAccount->name,
                'timezone' => 'Europe/London',
                'default_country' => 'GB',
                'default_currency' => 'GBP',
                'billing_status' => 'active',
            ])
            ->assertRedirect();

        $account = $this->ukAccount->fresh();
        $this->assertTrue($account->is_active);
        $this->assertSame('active', $account->settings['billing_status']);
        $this->assertArrayNotHasKey('billing_locked_at', $account->settings);
    }

    public function test_campaign_create_inherits_updated_platform_defaults(): void
    {
        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->put(route('settings.update'), [
                'name' => $this->ukAccount->name,
                'timezone' => 'America/Toronto',
                'default_country' => 'CA',
                'default_currency' => 'CAD',
            ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('campaigns.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('defaults.country', 'CA')
                ->where('defaults.currency', 'CAD')
            );
    }

    public function test_prepay_can_be_disabled_from_settings(): void
    {
        $this->ukAccount->update([
            'settings' => array_merge($this->ukAccount->settings ?? [], ['require_buyer_prepay' => true]),
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->put(route('settings.update'), [
                'name' => $this->ukAccount->name,
                'timezone' => 'Europe/London',
                'default_country' => 'GB',
                'default_currency' => 'GBP',
                'require_buyer_prepay' => false,
            ])
            ->assertRedirect();

        $this->assertFalse($this->ukAccount->fresh()->settings['require_buyer_prepay']);
    }

    public function test_billing_due_date_persists(): void
    {
        $due = now()->addDays(14)->toDateString();

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->put(route('settings.update'), [
                'name' => $this->ukAccount->name,
                'timezone' => 'Europe/London',
                'default_country' => 'GB',
                'default_currency' => 'GBP',
                'billing_due_at' => $due,
            ])
            ->assertRedirect();

        $this->assertSame($due, $this->ukAccount->fresh()->settings['billing_due_at']);
    }

    public function test_invalid_timezone_is_rejected(): void
    {
        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->put(route('settings.update'), [
                'name' => $this->ukAccount->name,
                'timezone' => 'Not/A/Timezone',
                'default_country' => 'GB',
                'default_currency' => 'GBP',
            ])
            ->assertSessionHasErrors('timezone');
    }

    public function test_staff_without_settings_module_cannot_access_settings(): void
    {
        $staff = User::factory()->create([
            'account_id' => $this->ukAccount->id,
            'role' => UserRole::Staff,
            'allowed_modules' => ['reports'],
        ]);

        $this->ukHost()
            ->actingAs($staff)
            ->get(route('settings.edit'))
            ->assertForbidden();

        $this->ukHost()
            ->actingAs($staff)
            ->put(route('settings.update'), [
                'name' => 'Blocked',
                'timezone' => 'Europe/London',
                'default_country' => 'GB',
                'default_currency' => 'GBP',
            ])
            ->assertForbidden();
    }

    public function test_locked_platform_can_still_open_settings(): void
    {
        $this->ukAccount->update([
            'is_active' => false,
            'settings' => array_merge($this->ukAccount->settings ?? [], ['billing_status' => 'locked']),
        ]);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get(route('settings.edit'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('account.billing_status', 'locked')
            );
    }
}
