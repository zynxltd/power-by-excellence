<?php

namespace Tests\Feature;

use App\Enums\LeadStatus;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DeliveryLog;
use App\Models\Lead;
use App\Models\LeadEvent;
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

    public function test_super_admin_can_access_platform_events_with_pagination(): void
    {
        $this->withoutVite();

        $super = User::where('email', 'admin@powerbyexcellence.test')->first();

        $this->actingAs($super)
            ->get('/platform-events')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/PlatformEvents/Index')
                ->has('events.data')
                ->has('events.links')
                ->has('tenants')
                ->has('stats')
                ->has('levelOptions')
                ->has('categoryOptions')
                ->has('eventTypes')
                ->where('filters.days', 7)
            );
    }

    public function test_platform_events_support_advanced_filters(): void
    {
        $this->withoutVite();

        $campaign = Campaign::where('reference', 'loans-uk')->first();
        $lead = Lead::create([
            'account_id' => $campaign->account_id,
            'campaign_id' => $campaign->id,
            'status' => LeadStatus::Rejected,
            'field_data' => ['email' => 'filter@test.test'],
            'received_at' => now(),
        ]);

        LeadEvent::create([
            'lead_id' => $lead->id,
            'event_type' => 'dedupe.rejected',
            'level' => 'warning',
            'message' => 'Duplicate detected on email',
            'payload' => ['field' => 'email'],
        ]);

        $super = User::where('email', 'admin@powerbyexcellence.test')->first();

        $this->actingAs($super)
            ->get('/platform-events?category=dedupe&level=warning&q=Duplicate')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/PlatformEvents/Index')
                ->where('filters.category', 'dedupe')
                ->where('filters.level', 'warning')
                ->where('events.data', fn ($rows) => collect($rows)->contains(
                    fn ($row) => $row['event_type'] === 'dedupe.rejected'
                        && $row['payload']['field'] === 'email'
                ))
                ->where('stats.warnings', fn ($v) => $v >= 1)
            );
    }

    public function test_tenant_admin_cannot_access_platform_events(): void
    {
        $admin = User::where('email', 'uk@powerbyexcellence.test')->first();

        $this->withServerVariables(['HTTP_HOST' => 'excellence-uk.powerbyexcellence.test'])
            ->actingAs($admin)
            ->get('/platform-events')
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
                ->has('stats')
                ->has('filters')
                ->has('tenants')
                ->has('typeOptions')
                ->where('filters.days', 1)
            );
    }

    public function test_live_feed_supports_type_filter(): void
    {
        $this->withoutVite();

        $super = User::where('email', 'admin@powerbyexcellence.test')->first();

        $this->actingAs($super)
            ->get('/live-feed?type=access&days=7')
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->component('Admin/LiveFeed/Index')
                ->where('filters.type', 'access')
                ->where('liveFeed.data', fn ($rows) => collect($rows)->every(fn ($row) => $row['type'] === 'access'))
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
