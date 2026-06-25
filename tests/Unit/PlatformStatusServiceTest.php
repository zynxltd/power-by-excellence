<?php

namespace Tests\Unit;

use App\Services\Platform\PlatformOpsCheck;
use App\Services\Platform\PlatformStatusService;
use App\Services\Platform\ProcessingMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class PlatformStatusServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_critical_ops_check_yields_outage_status(): void
    {
        $ops = Mockery::mock(PlatformOpsCheck::class);
        $ops->shouldReceive('run')->andReturn([
            ['key' => 'database', 'label' => 'Database', 'status' => 'critical', 'message' => 'Down', 'hint' => null, 'command' => null],
        ]);
        $ops->shouldReceive('herdLinkStatus')->andReturn([
            'linked' => [], 'missing' => [], 'commands' => [], 'shell_script' => '', 'needs_linking' => false,
        ]);

        $processing = Mockery::mock(ProcessingMetrics::class);
        $processing->shouldReceive('avgProcessingMs')->andReturn(100);
        $processing->shouldReceive('p95ProcessingMs')->andReturn(150);
        $processing->shouldReceive('targetMs')->andReturn(200);
        $processing->shouldReceive('withinTarget')->andReturn(true);

        $service = new PlatformStatusService($ops, $processing);
        $result = $service->refresh();

        $this->assertSame('outage', $result['status']);
        $this->assertSame('Service disruption', $result['label']);
    }

    public function test_warning_ops_check_yields_degraded_status(): void
    {
        $ops = Mockery::mock(PlatformOpsCheck::class);
        $ops->shouldReceive('run')->andReturn([
            ['key' => 'queue', 'label' => 'Queue', 'status' => 'warning', 'message' => 'Failed jobs', 'hint' => null, 'command' => null],
        ]);
        $ops->shouldReceive('herdLinkStatus')->andReturn([
            'linked' => [], 'missing' => [], 'commands' => [], 'shell_script' => '', 'needs_linking' => false,
        ]);

        $processing = Mockery::mock(ProcessingMetrics::class);
        $processing->shouldReceive('avgProcessingMs')->andReturn(100);
        $processing->shouldReceive('p95ProcessingMs')->andReturn(150);
        $processing->shouldReceive('targetMs')->andReturn(200);
        $processing->shouldReceive('withinTarget')->andReturn(true);

        $service = new PlatformStatusService($ops, $processing);
        $result = $service->refresh();

        $this->assertSame('degraded', $result['status']);
    }

    public function test_failed_jobs_without_warnings_still_degrades(): void
    {
        $this->seed(\Database\Seeders\PlatformSeeder::class);

        if (! DB::getSchemaBuilder()->hasTable('failed_jobs')) {
            $this->markTestSkipped('failed_jobs table not present.');
        }

        DB::table('failed_jobs')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'connection' => 'redis',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'test',
            'failed_at' => now(),
        ]);

        $result = app(PlatformStatusService::class)->refresh();

        $this->assertSame('degraded', $result['status']);
        $this->assertGreaterThan(0, $result['metrics']['failed_jobs']);
    }

    public function test_public_payload_strips_sensitive_ops_fields(): void
    {
        $service = app(PlatformStatusService::class);
        $payload = $service->publicPayload([
            'status' => 'operational',
            'label' => 'All systems operational',
            'checked_at' => now()->toIso8601String(),
            'uptime_30d' => 100.0,
            'checks' => [
                ['key' => 'db', 'label' => 'Database', 'status' => 'ok', 'message' => 'OK', 'hint' => 'secret', 'command' => 'php artisan secret'],
            ],
            'metrics' => ['failed_jobs' => 0],
        ]);

        $this->assertArrayNotHasKey('hint', $payload['components'][0]);
        $this->assertArrayNotHasKey('command', $payload['components'][0]);
        $this->assertArrayHasKey('name', $payload['components'][0]);
    }
}
