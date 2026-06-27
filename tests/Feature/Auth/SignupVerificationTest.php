<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\User;
use App\Support\Auth\SignupVerification;
use App\Services\Auth\PhoneVerificationService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class SignupVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'messaging.email_verification_enabled' => true,
            'messaging.address_verification_enabled' => true,
        ]);
    }

    protected function createAccount(array $overrides = []): Account
    {
        return Account::create(array_merge([
            'name' => 'Test Platform',
            'slug' => 'test-'.uniqid(),
            'default_currency' => 'GBP',
            'default_country' => 'GB',
            'is_active' => true,
        ], $overrides));
    }

    public function test_dashboard_redirects_unverified_user_to_email_verification(): void
    {
        $account = $this->createAccount();
        $user = User::factory()->signupIncomplete()->create([
            'role' => UserRole::AccountAdmin,
            'account_id' => $account->id,
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_email_verified_user_skips_phone_when_sms_disabled(): void
    {
        $account = $this->createAccount();
        $user = User::factory()->create([
            'role' => UserRole::AccountAdmin,
            'account_id' => $account->id,
            'email_verified_at' => now(),
            'phone_verified_at' => null,
            'address_verified_at' => null,
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('verification.address'));
    }

    public function test_email_verified_user_is_redirected_to_phone_when_sms_enabled(): void
    {
        config(['messaging.phone_verification_enabled' => true]);

        $account = $this->createAccount();
        $user = User::factory()->create([
            'role' => UserRole::AccountAdmin,
            'account_id' => $account->id,
            'email_verified_at' => now(),
            'phone_verified_at' => null,
            'address_verified_at' => null,
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('verification.phone'));
    }

    public function test_phone_verification_accepts_valid_code(): void
    {
        config(['messaging.phone_verification_enabled' => true]);
        $account = $this->createAccount();
        $user = User::factory()->create([
            'role' => UserRole::AccountAdmin,
            'account_id' => $account->id,
            'email_verified_at' => now(),
            'phone_verified_at' => null,
        ]);

        Cache::put('signup_phone_verification:'.$user->id, [
            'code' => '654321',
            'phone' => '+447700900123',
        ], now()->addMinutes(10));

        $this->actingAs($user)
            ->post(route('verification.phone.verify'), ['code' => '654321'])
            ->assertRedirect(route('verification.address'));

        $user->refresh();
        $this->assertTrue($user->hasVerifiedPhone());
        $this->assertSame('+447700900123', $user->phone);
    }

    public function test_phone_verification_send_stores_pending_code(): void
    {
        config(['messaging.phone_verification_enabled' => true]);
        $account = $this->createAccount();
        $user = User::factory()->create([
            'role' => UserRole::AccountAdmin,
            'account_id' => $account->id,
            'email_verified_at' => now(),
            'phone_verified_at' => null,
        ]);

        $this->actingAs($user)
            ->post(route('verification.phone.send'), ['phone' => '07700900123'])
            ->assertRedirect()
            ->assertSessionHas('status', 'verification-code-sent');

        $payload = Cache::get('signup_phone_verification:'.$user->id);
        $this->assertIsArray($payload);
        $this->assertSame('+447700900123', $payload['phone']);
        $this->assertSame(6, strlen($payload['code']));
    }

    public function test_address_verification_completes_signup(): void
    {
        $account = $this->createAccount(['default_country' => 'GB']);
        $user = User::factory()->create([
            'role' => UserRole::AccountAdmin,
            'account_id' => $account->id,
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'phone' => '+447700900123',
            'address_verified_at' => null,
        ]);

        $this->actingAs($user)
            ->post(route('verification.address.store'), [
                'address_line1' => '10 Downing Street',
                'address_line2' => '',
                'city' => 'London',
                'region' => 'Greater London',
                'postcode' => 'sw1a 2aa',
                'country' => 'GB',
                'confirm_address' => true,
            ])
            ->assertRedirect(route('dashboard', absolute: false).'?verified=1');

        $user->refresh();
        $this->assertTrue($user->hasVerifiedAddress());
        $this->assertSame('SW1A 2AA', $user->postcode);
    }

    public function test_super_admin_skips_signup_verification(): void
    {
        $user = User::factory()->signupIncomplete()->create([
            'role' => UserRole::SuperAdmin,
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_email_verification_redirects_to_address_when_sms_disabled(): void
    {
        $account = $this->createAccount();
        $user = User::factory()->unverified()->create([
            'role' => UserRole::AccountAdmin,
            'account_id' => $account->id,
        ]);

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->actingAs($user)
            ->get($verificationUrl)
            ->assertRedirect(route('verification.address'));

        Event::assertDispatched(Verified::class);
    }

    public function test_email_verification_redirects_to_phone_when_sms_enabled(): void
    {
        config(['messaging.phone_verification_enabled' => true]);

        $account = $this->createAccount();
        $user = User::factory()->unverified()->create([
            'role' => UserRole::AccountAdmin,
            'account_id' => $account->id,
        ]);

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $this->actingAs($user)
            ->get($verificationUrl)
            ->assertRedirect(route('verification.phone'));

        Event::assertDispatched(Verified::class);
    }

    public function test_phone_verification_disabled_by_default_with_log_provider(): void
    {
        config([
            'messaging.sms_provider' => 'log',
            'messaging.phone_verification_enabled' => null,
        ]);

        $this->assertFalse(app(PhoneVerificationService::class)->isEnabled());
    }

    public function test_phone_normalization_supports_uk_local_numbers(): void
    {
        $service = app(PhoneVerificationService::class);

        $this->assertSame('+447700900123', $service->normalizePhone('07700900123'));
        $this->assertSame('+447700900123', $service->normalizePhone('+447700900123'));
    }

    public function test_signup_complete_when_email_and_address_verification_disabled(): void
    {
        config([
            'messaging.email_verification_enabled' => false,
            'messaging.address_verification_enabled' => false,
        ]);

        $account = $this->createAccount();
        $user = User::factory()->signupIncomplete()->create([
            'role' => UserRole::AccountAdmin,
            'account_id' => $account->id,
        ]);

        $this->assertTrue(SignupVerification::isComplete($user));
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue($user->hasVerifiedAddress());
    }
}
