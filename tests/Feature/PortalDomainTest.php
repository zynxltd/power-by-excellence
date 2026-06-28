<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\PlatformFeatureParity\PortalDomain;
use App\PlatformFeatureParity\PortalDomainVerification;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PortalDomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['web', 'auth'])->group(function () {
            Route::post('settings/portal-domain/verify', [\App\Http\Controllers\Admin\AccountSettingsController::class, 'verifyPortalDomain'])
                ->name('settings.portal-domain.verify');
        });
    }

    public function test_portal_host_uses_verified_custom_portal_domain(): void
    {
        $account = Account::create([
            'name' => 'White Label',
            'slug' => 'white-label',
            'domain' => 'legacy.white-label.test',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
            'settings' => [
                'custom_portal_domain' => 'portal.whitelabel.test',
                'custom_portal_domain_verified_at' => now()->toIso8601String(),
            ],
        ]);

        $this->assertSame('portal.whitelabel.test', PortalDomain::portalHost($account));
        $this->assertSame('portal.whitelabel.test', TenantResolver::portalHost($account));
    }

    public function test_portal_host_falls_back_when_custom_domain_not_verified(): void
    {
        $account = Account::create([
            'name' => 'Pending Domain',
            'slug' => 'pending-domain',
            'domain' => 'legacy.pending.test',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
            'settings' => ['custom_portal_domain' => 'portal.pending.test'],
        ]);

        $this->assertSame('legacy.pending.test', PortalDomain::portalHost($account));
        $this->assertSame('legacy.pending.test', TenantResolver::portalHost($account));
    }

    public function test_portal_host_falls_back_to_legacy_domain(): void
    {
        $account = Account::create([
            'name' => 'Legacy Domain',
            'slug' => 'legacy-domain',
            'domain' => 'leads.legacy.test',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
        ]);

        $this->assertSame('leads.legacy.test', PortalDomain::portalHost($account));
    }

    public function test_verified_custom_portal_domain_matches_host_and_resolves_tenant(): void
    {
        $account = Account::create([
            'name' => 'Branded',
            'slug' => 'branded',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
            'settings' => [
                'custom_portal_domain' => 'leads.branded.test',
                'custom_portal_domain_verified_at' => now()->toIso8601String(),
            ],
        ]);

        $this->assertTrue(PortalDomain::matches($account, 'leads.branded.test'));
        $this->assertFalse(PortalDomain::matches($account, 'other.example.test'));
        $this->assertTrue($account->is(TenantResolver::resolveFromHost('leads.branded.test')));
    }

    public function test_unverified_custom_portal_domain_does_not_resolve_tenant(): void
    {
        Account::create([
            'name' => 'Unverified',
            'slug' => 'unverified',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
            'settings' => ['custom_portal_domain' => 'leads.unverified.test'],
        ]);

        $this->assertNull(TenantResolver::resolveFromHost('leads.unverified.test'));
    }

    public function test_tenant_resolver_portal_url_uses_verified_custom_domain(): void
    {
        $account = Account::create([
            'name' => 'URL Tenant',
            'slug' => 'url-tenant',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
            'settings' => [
                'custom_portal_domain' => 'portal.url-tenant.test',
                'custom_portal_domain_verified_at' => now()->toIso8601String(),
            ],
        ]);

        $this->assertSame(
            'http://portal.url-tenant.test/buyer/login',
            TenantResolver::portalUrl($account, '/buyer/login')
        );
    }

    public function test_settings_update_clears_verification_when_domain_changes(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $account = Account::where('slug', 'excellence-uk')->first();
        $account->update([
            'settings' => array_merge($account->settings ?? [], [
                'custom_portal_domain' => 'portal.old.test',
                'custom_portal_domain_verified_at' => now()->toIso8601String(),
                'custom_portal_domain_verification_token' => 'old-token',
            ]),
        ]);

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($admin)
            ->put(route('settings.update'), [
                'name' => $account->name,
                'timezone' => $account->timezone,
                'default_country' => $account->default_country,
                'default_currency' => $account->default_currency,
                'require_buyer_prepay' => false,
                'supplier_iframe_embed' => false,
                'billing_alert_emails' => '',
                'default_low_credit_alert' => '',
                'buyer_portal_locale' => 'en',
                'custom_portal_domain' => 'portal.new.test',
            ])
            ->assertRedirect();

        $account->refresh();

        $this->assertSame('portal.new.test', $account->settings['custom_portal_domain']);
        $this->assertArrayNotHasKey('custom_portal_domain_verified_at', $account->settings);
        $this->assertArrayNotHasKey('custom_portal_domain_verification_token', $account->settings);
    }

    public function test_verify_portal_domain_marks_account_verified_via_cname(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $account = Account::where('slug', 'excellence-uk')->first();
        $account->update([
            'settings' => array_merge($account->settings ?? [], [
                'custom_portal_domain' => 'portal.verify.test',
            ]),
        ]);

        $verification = new PortalDomainVerification(function (string $host, int $type) use ($account) {
            if ($type === DNS_CNAME && $host === 'portal.verify.test') {
                return [['target' => $account->slug.'.powerbyexcellence.test']];
            }

            return [];
        });
        $this->app->instance(PortalDomainVerification::class, $verification);

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($admin)
            ->post('/settings/portal-domain/verify')
            ->assertRedirect()
            ->assertSessionHas('success');

        $account->refresh();

        $this->assertTrue(PortalDomain::isVerified($account));
        $this->assertSame('portal.verify.test', PortalDomain::portalHost($account));
    }

    public function test_verify_portal_domain_accepts_txt_token(): void
    {
        $account = Account::create([
            'name' => 'TXT Verify',
            'slug' => 'txt-verify',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
            'settings' => [
                'custom_portal_domain' => 'portal.txt.test',
                'custom_portal_domain_verification_token' => 'verify-token-123',
            ],
        ]);

        $verification = new PortalDomainVerification(function (string $host, int $type) {
            if ($type === DNS_TXT && $host === '_portal-verify.portal.txt.test') {
                return [['txt' => 'verify-token-123']];
            }

            return [];
        });

        $result = $verification->verify($account);

        $this->assertTrue($result['verified']);
        $this->assertSame('txt', $result['method']);
        $this->assertTrue(PortalDomain::isVerified($account->fresh()));
    }
}
