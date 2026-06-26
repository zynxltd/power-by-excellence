<?php

namespace Tests\Unit;

use App\Services\Platform\PlatformOpsCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class PlatformOpsCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
        app(PlatformOpsCheck::class)->run(fresh: true);
    }

    public function test_run_returns_grouped_infrastructure_speed_and_quality_checks(): void
    {
        $checks = app(PlatformOpsCheck::class)->run(fresh: true);

        $this->assertGreaterThanOrEqual(10, count($checks));

        $keys = collect($checks)->pluck('key')->all();
        $this->assertContains('database', $keys);
        $this->assertContains('cache', $keys);
        $this->assertContains('processing_speed', $keys);
        $this->assertContains('post_quality', $keys);

        $categories = collect($checks)->pluck('category')->unique()->sort()->values()->all();
        $this->assertEquals(['infrastructure', 'quality', 'speed'], $categories);
    }

    public function test_checks_are_cached_for_performance(): void
    {
        $service = app(PlatformOpsCheck::class);
        $service->run(fresh: true);

        $this->assertNotNull(Cache::get(PlatformOpsCheck::CHECKS_CACHE_KEY));

        $cached = $service->run();
        $this->assertIsArray($cached);
        $this->assertNotEmpty($cached);
    }

    public function test_herd_status_is_cached_separately(): void
    {
        $service = app(PlatformOpsCheck::class);
        $herd = $service->herdLinkStatus(fresh: true);

        $this->assertArrayHasKey('needs_linking', $herd);
        $this->assertNotNull(Cache::get(PlatformOpsCheck::HERD_CACHE_KEY));
    }

    public function test_redis_queue_fallback_warns_to_start_horizon(): void
    {
        Config::set('platform.queue.fallback_active', true);

        $checks = app(PlatformOpsCheck::class)->run(fresh: true);
        $redis = collect($checks)->firstWhere('key', 'redis');
        $horizon = collect($checks)->firstWhere('key', 'horizon');

        $this->assertSame('warning', $redis['status']);
        $this->assertNull($redis['command']);
        $this->assertSame('warning', $horizon['status']);
        $this->assertSame('php artisan horizon', $horizon['command']);
    }
}
