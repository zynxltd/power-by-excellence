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
