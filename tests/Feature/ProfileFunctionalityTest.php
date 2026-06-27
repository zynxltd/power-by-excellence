<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\AdminModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProfileFunctionalityTest extends TestCase
{
    use RefreshDatabase;

    protected User $ukAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
        $this->ukAdmin = User::where('email', 'uk@powerbyexcellence.test')->first();
    }

    protected function ukHost()
    {
        return $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test']);
    }

    public function test_profile_routes_map_to_settings_module(): void
    {
        $this->assertSame('settings', AdminModules::moduleForRoute('profile.edit'));
        $this->assertSame('settings', AdminModules::moduleForRoute('profile.preferences'));
    }

    public function test_tenant_admin_profile_page_has_expected_sections(): void
    {
        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get('http://excellence-uk.powerbyexcellence.test/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Edit')
                ->where('twoFactorEnabled', false)
                ->has('preferences.theme')
                ->has('preferences.accent_color')
                ->has('accentOptions', 6)
            );
    }

    public function test_buyer_portal_user_can_update_profile_on_tenant_host(): void
    {
        $buyer = User::where('email', 'buyer-portal@excellence-uk.test')->first();

        $this->ukHost()
            ->actingAs($buyer)
            ->patch('/profile', [
                'name' => 'Buyer Updated Name',
                'email' => $buyer->email,
            ])
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('success', 'Profile updated.');

        $this->assertSame('Buyer Updated Name', $buyer->fresh()->name);
    }

    public function test_profile_rejects_duplicate_email(): void
    {
        $other = User::where('email', 'us@powerbyexcellence.test')->first();

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->patch('/profile', [
                'name' => $this->ukAdmin->name,
                'email' => $other->email,
            ])
            ->assertSessionHasErrors('email');
    }

    public function test_user_can_upload_and_remove_avatar(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->patch('/profile', [
                'name' => $this->ukAdmin->name,
                'email' => $this->ukAdmin->email,
                'avatar' => $file,
            ])
            ->assertRedirect(route('profile.edit'));

        $path = $this->ukAdmin->fresh()->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->patch('/profile', [
                'name' => $this->ukAdmin->name,
                'email' => $this->ukAdmin->email,
                'remove_avatar' => true,
            ])
            ->assertRedirect(route('profile.edit'));

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($this->ukAdmin->fresh()->avatar_path);
    }

    public function test_user_can_update_password(): void
    {
        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'NewPassword123!',
                'password_confirmation' => 'NewPassword123!',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Password updated.');
    }

    public function test_two_factor_can_be_enabled_and_disabled(): void
    {
        $google2fa = new \PragmaRX\Google2FA\Google2FA;

        $enable = $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('profile.two-factor.enable'), ['password' => 'password'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Scan the QR code and enter a code to confirm.');

        $pendingSecret = session('two_factor.pending_secret');
        $this->assertNotNull($pendingSecret);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('profile.two-factor.confirm'), [
                'password' => 'password',
                'code' => $google2fa->getCurrentOtp($pendingSecret),
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Two-factor authentication enabled.')
            ->assertSessionHas('recovery_codes');

        $user = $this->ukAdmin->fresh();
        $this->assertTrue($user->two_factor_enabled);
        $this->assertNotNull($user->two_factor_secret);
        $this->assertCount(8, $user->two_factor_recovery_codes);

        $this->ukHost()
            ->actingAs($user)
            ->withSession(['two_factor_verified' => $user->id])
            ->post(route('profile.two-factor.disable'), ['password' => 'password'])
            ->assertRedirect()
            ->assertSessionHas('success', 'Two-factor authentication disabled.');

        $user->refresh();
        $this->assertFalse($user->two_factor_enabled);
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
    }

    public function test_two_factor_enable_requires_correct_password(): void
    {
        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->post(route('profile.two-factor.enable'), ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');

        $this->assertFalse($this->ukAdmin->fresh()->two_factor_enabled);
    }

    public function test_invalid_theme_preference_is_rejected(): void
    {
        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->patch('/profile/preferences', [
                'theme' => 'sepia',
                'accent_color' => 'indigo',
            ])
            ->assertSessionHasErrors('theme');
    }

    public function test_staff_user_can_access_profile_without_other_modules(): void
    {
        $staff = User::factory()->create([
            'account_id' => $this->ukAdmin->account_id,
            'role' => UserRole::Staff,
            'allowed_modules' => ['reports'],
        ]);

        $this->ukHost()
            ->actingAs($staff)
            ->get('/campaigns')
            ->assertForbidden();

        $this->ukHost()
            ->actingAs($staff)
            ->get('/profile')
            ->assertOk();
    }

    public function test_shared_auth_preferences_reflect_saved_profile_settings(): void
    {
        $this->ukAdmin->update(['theme' => 'dark', 'accent_color' => 'rose']);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get('http://excellence-uk.powerbyexcellence.test/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('preferences.theme', 'dark')
                ->where('preferences.accent_color', 'rose')
                ->where('auth.preferences.theme', 'dark')
                ->where('auth.preferences.accent_color', 'rose')
            );
    }
}
