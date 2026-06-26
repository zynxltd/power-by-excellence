<?php

namespace App\Services\Platform;

use App\Models\Account;
use App\Models\PlatformNotification;
use App\Models\SystemErrorLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Syncs operational issues into super-admin system notifications (in-app bell + command center).
 */
class PlatformAdminAlertService
{
    public const ALERT_PLATFORM_STATUS = 'platform_status';

    public const ALERT_FAILED_JOBS = 'failed_jobs';

    public const ALERT_PRODUCTION_ERRORS = 'production_errors';

    public const ALERT_TENANT_HEALTH = 'tenant_health_critical';

    public const ALERT_QUEUE_BACKLOG = 'queue_backlog';

    public const ALERT_UNCAUGHT_EXCEPTION = 'uncaught_exception';

    public function __construct(
        protected PlatformNotificationService $notifications,
        protected PlatformOpsCheck $opsCheck,
        protected TenantHealth $tenantHealth,
    ) {}

    /**
     * @param  array<string, mixed>|null  $statusPayload
     */
    public function syncAll(?array $statusPayload = null): void
    {
        if (! $this->tableReady()) {
            return;
        }

        $checks = $this->opsCheck->run();
        $status = $statusPayload ?? app(PlatformStatusService::class)->current();

        $this->syncOpsCheckAlerts($checks);
        $this->notifications->syncHerdLinkingAlert($this->opsCheck->herdLinkStatus());
        $this->syncPlatformStatusAlert($status);
        $this->syncFailedJobsAlert((int) ($status['metrics']['failed_jobs'] ?? 0));
        $this->syncProductionErrorsAlert();
        $this->syncTenantHealthAlert();
        $this->syncQueueBacklogAlert((int) ($status['metrics']['pending_queue'] ?? 0));
    }

    public function notifyUncaughtException(Throwable $e): void
    {
        if (! $this->tableReady() || ! app()->environment('production')) {
            return;
        }

        $cooldown = (int) config('platform.admin_alerts.exception_alert_cooldown_seconds', 300);
        $fingerprint = sha1($e::class.'|'.$e->getMessage());
        $cacheKey = 'platform.admin_alert.exception.'.$fingerprint;

        if (! Cache::add($cacheKey, true, $cooldown)) {
            return;
        }

        $this->notifications->syncSystemAlert(
            self::ALERT_UNCAUGHT_EXCEPTION,
            'Production exception: '.$e::class,
            str($e->getMessage())->limit(500)->toString(),
            'critical',
            [
                'exception_class' => $e::class,
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ],
        );
    }

    /**
     * @param  list<array{key: string, label: string, status: string, message: string, hint: ?string, command: ?string, category: string}>  $checks
     */
    protected function syncOpsCheckAlerts(array $checks): void
    {
        $activeKeys = [];

        foreach ($checks as $check) {
            if (($check['status'] ?? 'ok') === 'ok') {
                continue;
            }

            $key = 'ops_check_'.$check['key'];
            $activeKeys[] = $key;

            $severity = ($check['status'] ?? '') === 'critical' ? 'critical' : 'warning';
            $body = $check['message'];
            if (! empty($check['hint'])) {
                $body .= ' — '.$check['hint'];
            }

            $this->notifications->syncSystemAlert(
                $key,
                $check['label'].' — '.ucfirst($check['status']),
                $body,
                $severity,
                [
                    'check_key' => $check['key'],
                    'category' => $check['category'] ?? 'infrastructure',
                    'command' => $check['command'] ?? null,
                ],
            );
        }

        $this->clearStaleAlerts('ops_check_', $activeKeys);
    }

    /**
     * @param  array<string, mixed>  $status
     */
    protected function syncPlatformStatusAlert(array $status): void
    {
        $overall = (string) ($status['status'] ?? 'operational');

        if ($overall === 'operational') {
            $this->notifications->clearSystemAlert(self::ALERT_PLATFORM_STATUS);

            return;
        }

        $severity = $overall === 'outage' ? 'critical' : 'warning';
        $label = (string) ($status['label'] ?? 'Platform status changed');
        $failedJobs = (int) ($status['metrics']['failed_jobs'] ?? 0);
        $postRate = $status['metrics']['post_success_rate'] ?? null;

        $details = collect([
            $failedJobs > 0 ? "{$failedJobs} failed queue job(s)" : null,
            $postRate !== null ? "Post success rate {$postRate}% today" : null,
        ])->filter()->implode(' · ');

        $this->notifications->syncSystemAlert(
            self::ALERT_PLATFORM_STATUS,
            $label,
            $details !== '' ? $details : 'Review command center ops checks for details.',
            $severity,
            [
                'status' => $overall,
                'checked_at' => $status['checked_at'] ?? null,
            ],
        );
    }

    protected function syncFailedJobsAlert(int $count): void
    {
        $warn = (int) config('platform.admin_alerts.failed_jobs_warning', 1);
        $critical = (int) config('platform.admin_alerts.failed_jobs_critical', 10);

        if ($count < $warn) {
            $this->notifications->clearSystemAlert(self::ALERT_FAILED_JOBS);

            return;
        }

        $severity = $count >= $critical ? 'critical' : 'warning';

        $this->notifications->syncSystemAlert(
            self::ALERT_FAILED_JOBS,
            'Failed queue jobs',
            "{$count} job(s) in the failed queue — leads or webhooks may not have processed.",
            $severity,
            [
                'count' => $count,
                'command' => 'php artisan queue:retry all',
            ],
        );
    }

    protected function syncProductionErrorsAlert(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('system_error_logs')) {
            return;
        }

        $window = (int) config('platform.admin_alerts.production_errors_window_minutes', 15);
        $threshold = (int) config('platform.admin_alerts.production_errors_threshold', 3);
        $since = now()->subMinutes($window);

        $query = SystemErrorLog::withoutGlobalScopes()
            ->where('level', 'error')
            ->where('created_at', '>=', $since);

        if (app()->environment('production')) {
            $query->where('channel', 'platform');
        }

        $count = (int) $query->count();

        if ($count < $threshold) {
            $this->notifications->clearSystemAlert(self::ALERT_PRODUCTION_ERRORS);

            return;
        }

        $latest = (clone $query)->orderByDesc('id')->first();
        $severity = $count >= max($threshold * 3, 10) ? 'critical' : 'warning';

        $this->notifications->syncSystemAlert(
            self::ALERT_PRODUCTION_ERRORS,
            'Production errors detected',
            "{$count} error(s) in the last {$window} minutes. Latest: ".str($latest?->message ?? 'Unknown')->limit(200),
            $severity,
            [
                'count' => $count,
                'window_minutes' => $window,
                'latest_trace_id' => $latest?->trace_id,
            ],
        );
    }

    protected function syncTenantHealthAlert(): void
    {
        $criticalTenants = [];

        foreach (Account::where('is_active', true)->orderBy('name')->get(['id', 'name', 'brand_name', 'slug']) as $account) {
            if ($this->tenantHealth->status($account->id) === 'critical') {
                $criticalTenants[] = $account->brand_name ?: $account->name;
            }
        }

        if ($criticalTenants === []) {
            $this->notifications->clearSystemAlert(self::ALERT_TENANT_HEALTH);

            return;
        }

        $listed = implode(', ', array_slice($criticalTenants, 0, 5));
        if (count($criticalTenants) > 5) {
            $listed .= ' …';
        }

        $this->notifications->syncSystemAlert(
            self::ALERT_TENANT_HEALTH,
            count($criticalTenants).' tenant(s) in critical health',
            $listed.' — low post success, sell rate, or slow processing.',
            'critical',
            [
                'tenant_count' => count($criticalTenants),
                'tenants' => $criticalTenants,
            ],
        );
    }

    protected function syncQueueBacklogAlert(int $pending): void
    {
        $warn = (int) config('platform.admin_alerts.queue_backlog_warning', 100);
        $critical = (int) config('platform.admin_alerts.queue_backlog_critical', 500);

        if ($pending < $warn) {
            $this->notifications->clearSystemAlert(self::ALERT_QUEUE_BACKLOG);

            return;
        }

        $severity = $pending >= $critical ? 'critical' : 'warning';

        $this->notifications->syncSystemAlert(
            self::ALERT_QUEUE_BACKLOG,
            'Lead queue backlog',
            "{$pending} lead(s) pending or processing — check queue workers and Horizon.",
            $severity,
            ['pending' => $pending],
        );
    }

    /**
     * @param  list<string>  $activeKeys
     */
    protected function clearStaleAlerts(string $prefix, array $activeKeys): void
    {
        $stale = PlatformNotification::query()
            ->where('audience', 'super_admin')
            ->where('type', 'system')
            ->where('metadata->alert_key', 'like', $prefix.'%')
            ->get()
            ->filter(function ($notification) use ($activeKeys) {
                $key = $notification->metadata['alert_key'] ?? null;

                return is_string($key) && ! in_array($key, $activeKeys, true);
            });

        foreach ($stale as $notification) {
            $key = $notification->metadata['alert_key'] ?? null;
            if (is_string($key)) {
                $this->notifications->clearSystemAlert($key);
            }
        }
    }

    protected function tableReady(): bool
    {
        return DB::getSchemaBuilder()->hasTable('platform_notifications');
    }
}
