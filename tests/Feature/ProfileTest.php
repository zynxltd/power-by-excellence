<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $this->withoutVite();
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create(['role' => UserRole::SuperAdmin]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_profile_account_deletion_is_disabled_for_all_users(): void
    {
        $account = \App\Models\Account::create([
            'name' => 'Test Platform',
            'slug' => 'test-platform',
            'default_currency' => 'GBP',
            'default_country' => 'GB',
        ]);

        $roles = [
            UserRole::SuperAdmin,
            UserRole::AccountAdmin,
            UserRole::BuyerPortal,
            UserRole::SupplierPortal,
        ];

        foreach ($roles as $role) {
            $user = User::factory()->create([
                'role' => $role,
                'account_id' => $role === UserRole::SuperAdmin ? null : $account->id,
            ]);

            $this->actingAs($user)
                ->from('/profile')
                ->delete('/profile', ['password' => 'password'])
                ->assertForbidden();

            $this->assertNotNull($user->fresh());
        }
    }
}
