<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaign_rejects_invalid_country_and_shows_errors(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $response = $this->actingAs($admin)->post(route('campaigns.store'), [
            'name' => 'Bad Campaign',
            'reference' => 'bad-campaign',
            'country' => 'INVALID',
            'currency' => 'INVALID',
            'payout_amount' => 5,
            'floor_price' => 10,
        ]);

        $response->assertSessionHasErrors(['country', 'currency']);
    }

    public function test_campaign_rejects_duplicate_reference_per_account(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $response = $this->actingAs($admin)->post(route('campaigns.store'), [
            'name' => 'Duplicate',
            'reference' => 'auto-insurance-uk',
            'country' => 'GB',
            'currency' => 'GBP',
            'payout_amount' => 5,
            'floor_price' => 10,
        ]);

        $response->assertSessionHasErrors(['reference']);
    }

    public function test_campaign_store_with_empty_caps_succeeds(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $this->actingAs($admin)
            ->post(route('campaigns.store'), [
                'name' => 'Caps Empty Test',
                'reference' => 'caps-empty-test',
                'country' => 'GB',
                'currency' => 'GBP',
                'status' => 'active',
                'vertical_id' => '',
                'payout_amount' => 5,
                'floor_price' => 10,
                'sell_mode' => 'exclusive',
                'bidding_mode' => 'real_time_auction',
                'use_advanced_distribution' => false,
                'caps' => [
                    'daily' => '',
                    'hourly' => '',
                    'daily_spend_cap' => '',
                    'monthly_spend_cap' => '',
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('campaigns', [
            'reference' => 'caps-empty-test',
            'account_id' => $admin->account_id,
        ]);
    }

    public function test_super_admin_on_central_without_tenant_redirects_on_campaign_create(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $super = User::where('email', 'admin@powerbyexcellence.test')->first();

        $this->withServerVariables(['HTTP_HOST' => 'powerbyexcellence.test'])
            ->actingAs($super)
            ->get(route('campaigns.create'))
            ->assertRedirect(route('accounts.index'))
            ->assertSessionHas('error');

        $this->withServerVariables(['HTTP_HOST' => 'powerbyexcellence.test'])
            ->actingAs($super)
            ->post(route('campaigns.store'), [
                'name' => 'No Tenant',
                'reference' => 'no-tenant-campaign',
                'country' => 'GB',
                'currency' => 'GBP',
                'payout_amount' => 5,
                'floor_price' => 10,
            ])
            ->assertRedirect(route('accounts.index'))
            ->assertSessionHas('error');
    }

    public function test_account_settings_update_defaults(): void
    {
        $this->withoutVite();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $this->actingAs($admin)->put(route('settings.update'), [
            'name' => 'My Custom Platform',
            'timezone' => 'America/Chicago',
            'default_country' => 'US',
            'default_currency' => 'USD',
        ])->assertRedirect();

        $admin->account->refresh();
        $this->assertSame('US', $admin->account->default_country);
        $this->assertSame('USD', $admin->account->default_currency);
    }
}
