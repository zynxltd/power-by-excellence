<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ThemeFunctionalityTest extends TestCase
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

    public function test_new_users_default_to_light_theme_and_indigo_accent(): void
    {
        $this->assertSame('light', $this->ukAdmin->theme);
        $this->assertSame('indigo', $this->ukAdmin->accent_color);
    }

    public function test_profile_accent_options_match_validation_allowlist(): void
    {
        $allowed = ['violet', 'indigo', 'emerald', 'rose', 'amber', 'cyan'];

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get('http://excellence-uk.powerbyexcellence.test/profile')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('accentOptions', 6)
                ->where('accentOptions', fn ($options) => collect($options)->pluck('value')->all() === $allowed)
            );
    }

    public function test_preferences_update_persists_theme_and_accent(): void
    {
        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->from('/dashboard')
            ->patch('/profile/preferences', [
                'theme' => 'dark',
                'accent_color' => 'cyan',
            ])
            ->assertRedirect('/dashboard')
            ->assertSessionHas('success', 'Appearance preferences saved.');

        $user = $this->ukAdmin->fresh();
        $this->assertSame('dark', $user->theme);
        $this->assertSame('cyan', $user->accent_color);
    }

    public function test_preferences_reject_non_binary_theme_values(): void
    {
        foreach (['sepia', 'auto', 'high-contrast', ''] as $invalid) {
            $this->ukHost()
                ->actingAs($this->ukAdmin)
                ->patch('/profile/preferences', [
                    'theme' => $invalid,
                    'accent_color' => 'indigo',
                ])
                ->assertSessionHasErrors('theme');
        }
    }

    public function test_preferences_reject_unknown_accent_colours(): void
    {
        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->patch('/profile/preferences', [
                'theme' => 'light',
                'accent_color' => 'magenta',
            ])
            ->assertSessionHasErrors('accent_color');
    }

    public function test_shared_auth_preferences_reflect_database_values(): void
    {
        $this->ukAdmin->update(['theme' => 'dark', 'accent_color' => 'emerald']);

        $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.preferences.theme', 'dark')
                ->where('auth.preferences.accent_color', 'emerald')
            );
    }

    public function test_buyer_portal_user_can_save_dark_theme(): void
    {
        $buyer = User::where('email', 'buyer-portal@excellence-uk.test')->first();

        $this->ukHost()
            ->actingAs($buyer)
            ->patch('/profile/preferences', [
                'theme' => 'dark',
                'accent_color' => 'rose',
            ])
            ->assertRedirect();

        $buyer->refresh();
        $this->assertSame('dark', $buyer->theme);
        $this->assertSame('rose', $buyer->accent_color);
    }

    public function test_supplier_portal_user_can_save_dark_theme(): void
    {
        $supplier = User::where('email', 'supplier-portal@excellence-uk.test')->first();

        $this->ukHost()
            ->actingAs($supplier)
            ->patch('/profile/preferences', [
                'theme' => 'dark',
                'accent_color' => 'cyan',
            ])
            ->assertRedirect();

        $supplier->refresh();
        $this->assertSame('dark', $supplier->theme);
        $this->assertSame('cyan', $supplier->accent_color);
    }

    public function test_blade_bootstraps_server_theme_for_authenticated_users(): void
    {
        $this->ukAdmin->update(['theme' => 'dark', 'accent_color' => 'amber']);

        $html = $this->ukHost()
            ->actingAs($this->ukAdmin)
            ->get('/dashboard')
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('"dark"', $html);
        $this->assertStringContainsString('--accent-from', $html);
        $this->assertStringContainsString('#d97706', $html);
    }

    public function test_marketing_homepage_renders_without_auth_theme_payload(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-page', false);
    }
}
