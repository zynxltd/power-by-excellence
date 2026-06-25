<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilePreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_theme_and_accent(): void
    {
        $this->withoutVite();
        $user = User::factory()->create(['role' => UserRole::SuperAdmin, 'theme' => 'light', 'accent_color' => 'indigo']);

        $this->actingAs($user)
            ->patch('/profile/preferences', [
                'theme' => 'dark',
                'accent_color' => 'emerald',
            ])
            ->assertRedirect('/profile');

        $user->refresh();
        $this->assertSame('dark', $user->theme);
        $this->assertSame('emerald', $user->accent_color);
    }

    public function test_invalid_accent_rejected(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $this->actingAs($user)
            ->patch('/profile/preferences', [
                'theme' => 'light',
                'accent_color' => 'invalid',
            ])
            ->assertSessionHasErrors('accent_color');
    }
}
