<?php

namespace Tests\Feature;

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
}
