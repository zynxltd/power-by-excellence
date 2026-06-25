<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuthFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
    }

    public function test_tenant_admin_can_reset_password_on_tenant_host(): void
    {
        Notification::fake();

        $user = User::where('email', 'uk@powerbyexcellence.test')->first();
        $tenantUrl = 'http://excellence-uk.powerbyexcellence.test';

        $this->post("{$tenantUrl}/forgot-password", ['email' => $user->email])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user, $tenantUrl) {
            $mail = $notification->toMail($user);
            $url = $mail->actionUrl;
            $this->assertStringContainsString('excellence-uk.powerbyexcellence.test', $url);

            $path = parse_url($url, PHP_URL_PATH);
            $query = parse_url($url, PHP_URL_QUERY);
            $this->get("{$tenantUrl}{$path}?{$query}")->assertOk();

            $this->post("{$tenantUrl}/reset-password", [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'new-secure-password',
                'password_confirmation' => 'new-secure-password',
            ])->assertSessionHasNoErrors()->assertRedirect(route('login'));

            return true;
        });

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));

        $this->post("{$tenantUrl}/login", [
            'email' => $user->email,
            'password' => 'new-secure-password',
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_tenant_user_cannot_request_reset_on_central_host(): void
    {
        Notification::fake();

        $user = User::where('email', 'uk@powerbyexcellence.test')->first();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHasErrors('email');

        Notification::assertNothingSent();
    }

    public function test_tenant_user_cannot_request_reset_on_wrong_tenant(): void
    {
        $user = User::where('email', 'uk@powerbyexcellence.test')->first();

        $this->withServerVariables(['HTTP_HOST' => 'solar-us.powerbyexcellence.test'])
            ->post('http://solar-us.powerbyexcellence.test/forgot-password', [
                'email' => $user->email,
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_super_admin_can_reset_password_on_central_host(): void
    {
        Notification::fake();

        $user = User::where('email', 'admin@powerbyexcellence.test')->first();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
            $response = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'central-new-password',
                'password_confirmation' => 'central-new-password',
            ]);

            $response->assertSessionHasNoErrors()->assertRedirect(route('login'));

            return true;
        });

        $this->assertTrue(Hash::check('central-new-password', $user->fresh()->password));
    }

    public function test_buyer_portal_user_can_login_after_password_reset_on_tenant(): void
    {
        Notification::fake();

        $user = User::where('email', 'buyer-portal@excellence-uk.test')->first();
        $tenantUrl = 'http://excellence-uk.powerbyexcellence.test';

        $this->post("{$tenantUrl}/forgot-password", ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user, $tenantUrl) {
            $this->post("{$tenantUrl}/reset-password", [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'buyer-new-password',
                'password_confirmation' => 'buyer-new-password',
            ])->assertRedirect(route('login'));

            return true;
        });

        $this->post("{$tenantUrl}/login", [
            'email' => $user->email,
            'password' => 'buyer-new-password',
        ])->assertRedirect(route('portal.buyer.dashboard', absolute: false));
    }

    public function test_supplier_portal_user_can_login_after_password_reset_on_tenant(): void
    {
        Notification::fake();

        $user = User::where('email', 'supplier-portal@excellence-uk.test')->first();
        $tenantUrl = 'http://excellence-uk.powerbyexcellence.test';

        $this->post("{$tenantUrl}/forgot-password", ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user, $tenantUrl) {
            $this->post("{$tenantUrl}/reset-password", [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => 'supplier-new-password',
                'password_confirmation' => 'supplier-new-password',
            ])->assertRedirect(route('login'));

            return true;
        });

        $this->post("{$tenantUrl}/login", [
            'email' => $user->email,
            'password' => 'supplier-new-password',
        ])->assertRedirect(route('portal.supplier.dashboard', absolute: false));
    }

    public function test_suspended_user_cannot_login(): void
    {
        $user = User::where('email', 'uk@powerbyexcellence.test')->first();
        $user->update(['is_suspended' => true]);

        $this->post('http://excellence-uk.powerbyexcellence.test/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_invalid_reset_token_is_rejected(): void
    {
        $user = User::where('email', 'uk@powerbyexcellence.test')->first();
        $tenantUrl = 'http://excellence-uk.powerbyexcellence.test';

        $this->post("{$tenantUrl}/reset-password", [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'should-not-work',
            'password_confirmation' => 'should-not-work',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_guest_cannot_access_password_update_or_confirm(): void
    {
        $this->get('/confirm-password')->assertRedirect('/login');
        $this->put('/password', [])->assertRedirect('/login');
    }

    public function test_login_page_renders_on_tenant_host(): void
    {
        $this->get('http://excellence-uk.powerbyexcellence.test/login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/Login')
                ->where('isCentralHost', false)
                ->has('tenant')
            );
    }

    public function test_forgot_password_page_renders_on_tenant_host(): void
    {
        $this->get('http://excellence-uk.powerbyexcellence.test/forgot-password')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Auth/ForgotPassword')
                ->has('tenant')
            );
    }
}
