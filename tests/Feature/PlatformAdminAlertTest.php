<?php

namespace Tests\Feature;

use App\Models\PlatformNotification;
use App\Models\SystemErrorLog;
use App\Models\User;
use App\Services\Platform\PlatformAdminAlertService;
use App\Services\Platform\PlatformNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class PlatformAdminAlertTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\PlatformSeeder::class);
    }

    public function test_failed_jobs_alert_created_and_cleared(): void
    {
        $service = app(PlatformAdminAlertService::class);

        DB::table('failed_jobs')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'Test failure',
            'failed_at' => now(),
        ]);

        $service->syncAll([
            'status' => 'degraded',
            'label' => 'Degraded performance',
            'metrics' => ['failed_jobs' => 1, 'pending_queue' => 0],
        ]);

        $this->assertDatabaseHas('platform_notifications', [
            'type' => 'system',
            'audience' => 'super_admin',
            'metadata->alert_key' => PlatformAdminAlertService::ALERT_FAILED_JOBS,
        ]);

        DB::table('failed_jobs')->delete();

        $service->syncAll([
            'status' => 'operational',
            'label' => 'All systems operational',
            'metrics' => ['failed_jobs' => 0, 'pending_queue' => 0],
        ]);

        $this->assertDatabaseMissing('platform_notifications', [
            'type' => 'system',
            'metadata->alert_key' => PlatformAdminAlertService::ALERT_FAILED_JOBS,
        ]);
    }

    public function test_production_errors_alert_when_threshold_exceeded(): void
    {
        config(['platform.admin_alerts.production_errors_threshold' => 2]);

        foreach (range(1, 3) as $i) {
            SystemErrorLog::create([
                'channel' => 'platform',
                'level' => 'error',
                'context' => 'system',
                'message' => "Synthetic error {$i}",
                'trace_id' => (string) \Illuminate\Support\Str::uuid(),
            ]);
        }

        app(PlatformAdminAlertService::class)->syncAll([
            'status' => 'operational',
            'label' => 'All systems operational',
            'metrics' => ['failed_jobs' => 0, 'pending_queue' => 0],
        ]);

        $this->assertDatabaseHas('platform_notifications', [
            'type' => 'system',
            'severity' => 'warning',
            'metadata->alert_key' => PlatformAdminAlertService::ALERT_PRODUCTION_ERRORS,
        ]);
    }

    public function test_platform_status_degraded_alert(): void
    {
        app(PlatformAdminAlertService::class)->syncAll([
            'status' => 'outage',
            'label' => 'Service disruption',
            'checked_at' => now()->toIso8601String(),
            'metrics' => ['failed_jobs' => 5, 'pending_queue' => 12, 'post_success_rate' => 72.5],
        ]);

        $this->assertDatabaseHas('platform_notifications', [
            'type' => 'system',
            'severity' => 'critical',
            'title' => 'Service disruption',
            'metadata->alert_key' => PlatformAdminAlertService::ALERT_PLATFORM_STATUS,
        ]);
    }

    public function test_system_alert_links_to_command_center(): void
    {
        $super = User::where('email', 'admin@powerbyexcellence.test')->first();
        $notifications = app(PlatformNotificationService::class);

        $alert = $notifications->syncSystemAlert(
            'test_alert',
            'Test alert',
            'Details here',
            'warning',
        );

        $this->assertSame(
            route('command-center.index'),
            $notifications->hrefFor($super, $alert),
        );
    }

    public function test_uncaught_exception_alert_in_production_with_cooldown(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        Cache::flush();

        $service = app(PlatformAdminAlertService::class);
        $exception = new RuntimeException('Database connection lost');

        $service->notifyUncaughtException($exception);
        $service->notifyUncaughtException($exception);

        $this->assertSame(
            1,
            PlatformNotification::where('metadata->alert_key', PlatformAdminAlertService::ALERT_UNCAUGHT_EXCEPTION)->count(),
        );
    }

    public function test_sync_alerts_command_runs(): void
    {
        $this->artisan('platform:sync-alerts')
            ->assertSuccessful();
    }

    public function test_command_center_includes_system_alerts(): void
    {
        $this->withoutVite();

        app(PlatformNotificationService::class)->syncSystemAlert(
            'test_panel_alert',
            'Queue workers offline',
            'No workers detected',
            'critical',
        );

        $super = User::where('email', 'admin@powerbyexcellence.test')->first();

        $this->actingAs($super)
            ->get('/command-center')
            ->assertOk()
            ->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->has('systemAlerts')
                ->where('systemAlerts', fn ($alerts) => collect($alerts)->contains(
                    fn ($a) => $a['title'] === 'Queue workers offline'
                ))
            );
    }
}
