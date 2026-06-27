<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\Account;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
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

    protected function createAccount(): Account
    {
        return Account::create([
            'name' => 'Test Platform',
            'slug' => 'test-'.uniqid(),
            'default_currency' => 'GBP',
            'default_country' => 'GB',
            'is_active' => true,
        ]);
    }

    protected function unverifiedUser(): User
    {
        return User::factory()->unverified()->create([
            'role' => UserRole::AccountAdmin,
            'account_id' => $this->createAccount()->id,
        ]);
    }

    public function test_email_verification_screen_can_be_rendered(): void
    {
        $user = $this->unverifiedUser();

        $response = $this->actingAs($user)->get('/verify-email');

        $response->assertStatus(200);
    }

    public function test_email_can_be_verified(): void
    {
        $user = $this->unverifiedUser();

        Event::fake();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1($user->email)]
        );

        $response = $this->actingAs($user)->get($verificationUrl);

        Event::assertDispatched(Verified::class);
        $this->assertTrue($user->fresh()->hasVerifiedEmail());
        $response->assertRedirect(route('verification.address', absolute: false));
    }

    public function test_email_is_not_verified_with_invalid_hash(): void
    {
        $user = $this->unverifiedUser();

        $verificationUrl = URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->id, 'hash' => sha1('wrong-email')]
        );

        $this->actingAs($user)->get($verificationUrl);

        $this->assertFalse($user->fresh()->hasVerifiedEmail());
    }
}
