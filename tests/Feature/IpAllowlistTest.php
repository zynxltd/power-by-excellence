<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminIpAllowlist;
use App\Http\Middleware\SetAccountFromUser;
use App\Models\AccessLog;
use App\Models\Account;
use App\Models\User;
use App\Services\Security\AdminIpAllowlistPolicy;
use App\Services\Security\AdminIpAllowlistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class IpAllowlistTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Account $account;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->admin = User::where('email', 'uk@powerbyexcellence.test')->first();
        $this->account = Account::where('slug', 'excellence-uk')->first();
        $this->registerAllowlistProbeRoute();
    }

    protected function registerAllowlistProbeRoute(): void
    {
        Route::middleware([
            'web',
            SetAccountFromUser::class,
            EnsureAdminIpAllowlist::class,
        ])->get('/_test/admin-ip-allowlist', fn () => response()->json(['ok' => true]));
    }

    protected function probePath(): string
    {
        return '/_test/admin-ip-allowlist';
    }

    protected function ukHost(string $ip = '127.0.0.1')
    {
        return $this->withServerVariables([
            'HTTP_HOST' => 'excellence-uk.powerbyexcellence.test',
            'REMOTE_ADDR' => $ip,
        ]);
    }

    /**
     * @param  array<string, mixed>  $security
     */
    protected function securitySettings(array $security): void
    {
        $this->account->update([
            'settings' => array_merge($this->account->settings ?? [], [
                AdminIpAllowlistPolicy::SETTINGS_KEY => array_merge(
                    AdminIpAllowlistPolicy::defaults(),
                    $security,
                ),
            ]),
        ]);
        $this->account->refresh();
    }

    protected function baseSettingsPayload(): array
    {
        return [
            'name' => $this->account->name,
            'timezone' => $this->account->timezone,
            'default_country' => $this->account->default_country,
            'default_currency' => $this->account->default_currency,
        ];
    }

    public function test_allowed_ip_passes_admin_middleware(): void
    {
        config(['platform.security.admin_ip_allowlist_bypass' => false]);

        $this->securitySettings([
            'admin_ip_allowlist_enabled' => true,
            'admin_ip_allowlist' => ['203.0.113.10', '198.51.100.0/24'],
        ]);

        $this->ukHost('203.0.113.10')
            ->actingAs($this->admin)
            ->get($this->probePath())
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_cidr_range_allows_matching_ip(): void
    {
        config(['platform.security.admin_ip_allowlist_bypass' => false]);

        $this->securitySettings([
            'admin_ip_allowlist_enabled' => true,
            'admin_ip_allowlist' => ['198.51.100.0/24'],
        ]);

        $this->ukHost('198.51.100.42')
            ->actingAs($this->admin)
            ->get($this->probePath())
            ->assertOk();
    }

    public function test_blocked_ip_returns_403_and_security_log(): void
    {
        config(['platform.security.admin_ip_allowlist_bypass' => false]);

        $this->securitySettings([
            'admin_ip_allowlist_enabled' => true,
            'admin_ip_allowlist' => ['203.0.113.10'],
        ]);

        $this->ukHost('8.8.8.8')
            ->actingAs($this->admin)
            ->getJson($this->probePath())
            ->assertForbidden();

        $this->assertDatabaseHas('access_logs', [
            'account_id' => $this->account->id,
            'user_id' => $this->admin->id,
            'action' => 'admin_ip_blocked',
            'ip_address' => '8.8.8.8',
        ]);
    }

    public function test_disabled_allowlist_bypasses_enforcement(): void
    {
        config(['platform.security.admin_ip_allowlist_bypass' => false]);

        $this->securitySettings([
            'admin_ip_allowlist_enabled' => false,
            'admin_ip_allowlist' => ['203.0.113.10'],
        ]);

        $this->ukHost('8.8.8.8')
            ->actingAs($this->admin)
            ->get($this->probePath())
            ->assertOk();
    }

    public function test_config_bypass_skips_enforcement(): void
    {
        config(['platform.security.admin_ip_allowlist_bypass' => true]);

        $this->securitySettings([
            'admin_ip_allowlist_enabled' => true,
            'admin_ip_allowlist' => ['203.0.113.10'],
        ]);

        $this->ukHost('8.8.8.8')
            ->actingAs($this->admin)
            ->get($this->probePath())
            ->assertOk();

        $this->assertSame(0, AccessLog::where('action', 'admin_ip_blocked')->count());
    }

    public function test_settings_update_persists_security_policy(): void
    {
        $this->ukHost('203.0.113.55')
            ->actingAs($this->admin)
            ->put(route('settings.update'), array_merge($this->baseSettingsPayload(), [
                'security' => [
                    'admin_ip_allowlist_enabled' => true,
                    'admin_ip_allowlist_text' => "203.0.113.55\n198.51.100.0/24",
                ],
            ]))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->account->refresh();
        $policy = AdminIpAllowlistPolicy::forAccount($this->account);

        $this->assertTrue($policy['admin_ip_allowlist_enabled']);
        $this->assertSame(['203.0.113.55', '198.51.100.0/24'], $policy['admin_ip_allowlist']);
    }

    public function test_allowlist_service_matches_cidr_and_exact_ip(): void
    {
        $service = app(AdminIpAllowlistService::class);

        $allowlist = ['203.0.113.10', '198.51.100.0/24'];

        $this->assertTrue($service->allows('203.0.113.10', $allowlist));
        $this->assertTrue($service->allows('198.51.100.200', $allowlist));
        $this->assertFalse($service->allows('8.8.8.8', $allowlist));
    }
}
