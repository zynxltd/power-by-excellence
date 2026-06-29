<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Services\Security\TwoFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TwoFactorLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    protected function applyStaffPolicy(int $graceDays = 0, ?string $enabledAt = null): Account
    {
        $account = Account::where('slug', 'excellence-uk')->first();
        $settings = $account->settings ?? [];
        $settings['require_2fa_for_staff'] = true;
        $settings['two_factor_grace_days'] = $graceDays;
        $settings['require_2fa_for_staff_enabled_at'] = $enabledAt ?? now()->subDay()->toIso8601String();
        $account->update(['settings' => $settings]);

        return $account;
    }

    protected function applyPortalPolicy(int $graceDays = 0, ?string $enabledAt = null): Account
    {
        $account = Account::where('slug', 'excellence-uk')->first();
        $settings = $account->settings ?? [];
        $settings['require_2fa_for_portal'] = true;
        $settings['two_factor_grace_days'] = $graceDays;
        $settings['require_2fa_for_portal_enabled_at'] = $enabledAt ?? now()->subDay()->toIso8601String();
        $account->update(['settings' => $settings]);

        return $account;
    }

    public function test_login_with_two_factor_redirects_to_challenge(): void
    {
        $user = User::where('email', 'uk@powerbyexcellence.test')->first();
        $secret = app(TwoFactorService::class)->generateSecret();

        $user->forceFill([
            'two_factor_enabled' => true,
            'two_factor_secret' => $secret,
        ])->save();

        $response = $this->post('http://excellence-uk.powerbyexcellence.test/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertTrue($response->isRedirect());
        $this->assertStringContainsString('two-factor-challenge', $response->headers->get('Location'));
        $this->assertGuest('web');
        $this->assertSame($user->id, session('login.id'));
    }

    public function test_two_factor_challenge_completes_login(): void
    {
        $user = User::where('email', 'uk@powerbyexcellence.test')->first();
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();

        $user->forceFill([
            'two_factor_enabled' => true,
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => ['RECOVERY1234'],
        ])->save();

        $code = $google2fa->getCurrentOtp($secret);

        $this->withSession(['login.id' => $user->id])
            ->post('http://excellence-uk.powerbyexcellence.test/two-factor-challenge', ['code' => $code])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
        $this->assertSame($user->id, session('two_factor_verified'));
    }

    public function test_recovery_code_completes_login(): void
    {
        $user = User::where('email', 'uk@powerbyexcellence.test')->first();
        $user->forceFill([
            'two_factor_enabled' => true,
            'two_factor_secret' => app(TwoFactorService::class)->generateSecret(),
            'two_factor_recovery_codes' => ['RECOVERY1234'],
        ])->save();

        $this->withSession(['login.id' => $user->id])
            ->post('http://excellence-uk.powerbyexcellence.test/two-factor-recovery', ['recovery_code' => 'RECOVERY1234'])
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticatedAs($user);
    }

    public function test_staff_blocked_when_require_2fa_for_staff_on_and_user_has_no_2fa(): void
    {
        $this->applyStaffPolicy(graceDays: 0);

        $user = User::where('email', 'uk@powerbyexcellence.test')->first();
        $user->forceFill([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
        ])->save();

        $this->ukHost()
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('warning');
    }

    public function test_portal_user_blocked_when_require_2fa_for_portal_on(): void
    {
        $this->applyPortalPolicy(graceDays: 0);

        $user = User::where('email', 'buyer-portal@excellence-uk.test')->first();
        $user->forceFill([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
        ])->save();

        $this->ukHost()
            ->actingAs($user)
            ->get(route('portal.buyer.dashboard'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('warning');
    }

    public function test_grace_period_allows_access_before_deadline(): void
    {
        $this->applyStaffPolicy(graceDays: 7, enabledAt: now()->toIso8601String());

        $user = User::where('email', 'uk@powerbyexcellence.test')->first();
        $user->forceFill([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
        ])->save();

        $this->ukHost()
            ->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_access_restored_after_enrolling_two_factor(): void
    {
        $this->applyStaffPolicy(graceDays: 0);

        $user = User::where('email', 'uk@powerbyexcellence.test')->first();
        $secret = app(TwoFactorService::class)->generateSecret();

        $user->forceFill([
            'two_factor_enabled' => true,
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => ['RECOVERY1234'],
        ])->save();

        $this->ukHost()
            ->actingAs($user)
            ->withSession(['two_factor_verified' => $user->id])
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_policy_blocks_disabling_two_factor_when_required(): void
    {
        $this->applyStaffPolicy(graceDays: 0);

        $user = User::where('email', 'uk@powerbyexcellence.test')->first();
        $secret = app(TwoFactorService::class)->generateSecret();
        $user->forceFill([
            'two_factor_enabled' => true,
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => ['RECOVERY1234'],
        ])->save();

        $this->ukHost()
            ->actingAs($user)
            ->withSession(['two_factor_verified' => $user->id])
            ->post(route('profile.two-factor.disable'), ['password' => 'password'])
            ->assertRedirect()
            ->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->two_factor_enabled);
    }
}
