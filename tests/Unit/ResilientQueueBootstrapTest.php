<?php

namespace Tests\Unit;

use App\Support\Platform\ResilientQueueBootstrap;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class ResilientQueueBootstrapTest extends TestCase
{
    public function test_database_preferred_skips_redis(): void
    {
        Config::set('platform.queue.preferred_connection', 'database');
        Config::set('platform.queue.redis_fallback', true);
        Config::set('platform.queue.fallback_active', false);

        ResilientQueueBootstrap::apply();

        $this->assertSame('database', config('queue.default'));
        $this->assertFalse(config('platform.queue.fallback_active'));
    }
}
