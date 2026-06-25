<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\DistributionConfig;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
    }

    public function test_automation_routing_scoped_to_tenant(): void
    {
        $caAdmin = User::where('email', 'ca@powerbyexcellence.test')->first();

        $response = $this->withServerVariables(['HTTP_HOST' => 'insurance-ca.powerbyexcellence.test'])
            ->actingAs($caAdmin)
            ->get('/automation')
            ->assertOk();

        $overview = $response->viewData('page')['props']['routingOverview'] ?? [];

        foreach ($overview as $config) {
            $this->assertStringContainsString('ca', strtolower($config['campaign']['reference'] ?? ''));
        }
    }

    public function test_distribution_show_404_for_other_tenant_config(): void
    {
        $caAdmin = User::where('email', 'ca@powerbyexcellence.test')->first();
        $ukCampaign = Campaign::withoutGlobalScopes()->where('reference', 'auto-insurance-uk')->first();
        $ukConfig = DistributionConfig::where('campaign_id', $ukCampaign->id)->first();

        $this->withServerVariables(['HTTP_HOST' => 'insurance-ca.powerbyexcellence.test'])
            ->actingAs($caAdmin)
            ->get("/distribution/{$ukConfig->id}")
            ->assertNotFound();
    }

    public function test_tenant_admin_sees_own_distribution_flow(): void
    {
        $caAdmin = User::where('email', 'ca@powerbyexcellence.test')->first();
        $caCampaign = Campaign::where('reference', 'auto-ca')->first();
        $caConfig = DistributionConfig::where('campaign_id', $caCampaign->id)->where('is_active', true)->first();

        if (! $caConfig) {
            $this->markTestSkipped('No active distribution config for Canada tenant in seeder.');
        }

        $this->withServerVariables(['HTTP_HOST' => 'insurance-ca.powerbyexcellence.test'])
            ->actingAs($caAdmin)
            ->get("/distribution/{$caConfig->id}")
            ->assertOk();
    }

    public function test_uk_tenant_does_not_see_ca_campaigns_on_automation(): void
    {
        $ukAdmin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $response = $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($ukAdmin)
            ->get('/automation')
            ->assertOk();

        $overview = $response->viewData('page')['props']['routingOverview'] ?? [];

        foreach ($overview as $config) {
            $this->assertStringNotContainsString('auto-ca', $config['campaign']['reference'] ?? '');
        }
    }
}
