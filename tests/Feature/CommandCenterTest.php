<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CommandCenterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
    }

    public function test_super_admin_can_access_command_center_with_delivery_stats(): void
    {
        $this->withoutVite();

        $campaign = Campaign::where('reference', 'loans-uk')->first();
        $delivery = Delivery::where('campaign_id', $campaign->id)->first();
        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Accepted,
            'field_data' => ['email' => 'cc@test.test'],
            'received_at' => now(),
        ]);

        DeliveryLog::create([
            'lead_id' => $lead->id,
            'delivery_id' => $delivery->id,
            'status' => 'success',
            'ping_request' => ['test' => true],
            'duration_ms' => 150,
        ]);

        $super = User::where('email', 'admin@powerbyexcellence.test')->first();

        $this->actingAs($super)
            ->get('/command-center')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/CommandCenter/Index')
                ->has('platformStats')
                ->has('tenants')
                ->has('opsChecks')
                ->where('platformStats.pings_today', fn ($v) => $v >= 1)
            );
    }

    public function test_tenant_admin_cannot_access_command_center(): void
    {
        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($admin)
            ->get('/command-center')
            ->assertForbidden();
    }

    public function test_tenant_admin_cannot_access_partner_platforms(): void
    {
        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($admin)
            ->get(route('accounts.index'))
            ->assertForbidden();
    }

    public function test_live_feed_page_supports_pagination(): void
    {
        $this->withoutVite();

        $super = User::where('email', 'admin@powerbyexcellence.test')->first();

        $this->actingAs($super)
            ->get('/live-feed?page=1')
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Admin/LiveFeed/Index')
                ->has('liveFeed.data')
                ->has('liveFeed.links')
            );
    }

    public function test_platform_check_command_runs(): void
    {
        $this->artisan('platform:check')
            ->assertSuccessful();
    }

    public function test_platform_link_tenants_command_dry_run(): void
    {
        $this->artisan('platform:link-tenants', ['--dry-run' => true])
            ->assertSuccessful();
    }
}
