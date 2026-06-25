<?php

namespace Tests\Feature;

use App\Models\PlatformStatusSnapshot;
use App\Services\Platform\PlatformStatusService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SystemStatusTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        $this->withoutVite();
    }

    public function test_public_status_page_loads(): void
    {
        $this->get(route('status.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Marketing/Status')
                ->has('status')
                ->has('status.components')
                ->where('status.status', fn ($v) => in_array($v, ['operational', 'degraded', 'outage'], true))
            );
    }

    public function test_public_status_json_endpoint(): void
    {
        $this->get(route('status.json'))
            ->assertOk()
            ->assertJsonStructure([
                'status',
                'label',
                'checked_at',
                'uptime_30d',
                'components',
                'metrics',
            ]);
    }

    public function test_status_snapshot_command_refreshes_cache(): void
    {
        Cache::forget(PlatformStatusService::CACHE_KEY);

        $this->artisan('platform:status-snapshot')
            ->assertSuccessful();

        $this->assertNotNull(Cache::get(PlatformStatusService::CACHE_KEY));
    }

    public function test_daily_snapshot_persists_to_database(): void
    {
        $this->artisan('platform:status-snapshot', ['--persist' => true])
            ->assertSuccessful();

        $this->assertDatabaseCount('platform_status_snapshots', 1);
        $this->assertContains(PlatformStatusSnapshot::first()->status, ['operational', 'degraded', 'outage']);
    }

    public function test_home_page_shares_system_status_on_central_host(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('systemStatus'));
    }

    public function test_uptime_calculated_from_daily_snapshots(): void
    {
        PlatformStatusSnapshot::create([
            'snapshot_date' => today()->subDays(2),
            'status' => 'operational',
            'payload' => ['status' => 'operational'],
            'checked_at' => now()->subDays(2),
        ]);

        PlatformStatusSnapshot::create([
            'snapshot_date' => today()->subDay(),
            'status' => 'degraded',
            'payload' => ['status' => 'degraded'],
            'checked_at' => now()->subDay(),
        ]);

        $uptime = app(PlatformStatusService::class)->refresh()['uptime_30d'];

        $this->assertEquals(50.0, $uptime);
    }
}
