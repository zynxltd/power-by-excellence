<?php

namespace App\Services\Platform;

use App\Models\DeliveryLog;
use App\Models\Lead;
use App\Models\PlatformStatusSnapshot;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PlatformStatusService
{
    public const CACHE_KEY = 'platform.status';

    public function __construct(
        protected PlatformOpsCheck $opsCheck,
        protected ProcessingMetrics $processingMetrics,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function current(): array
    {
        $cached = Cache::get(self::CACHE_KEY);

        if (is_array($cached) && $this->isFresh($cached)) {
            return $cached;
        }

        return $this->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function refresh(bool $persistDaily = false): array
    {
        $checks = $this->opsCheck->run(fresh: true);
        $failedJobs = $this->failedJobsCount();
        $pendingQueue = Lead::withoutGlobalScopes()->whereIn('status', ['pending', 'processing'])->count();
        $leadsToday = Lead::withoutGlobalScopes()->whereDate('received_at', today())->count();
        $postsToday = DeliveryLog::whereDate('created_at', today())->whereNotNull('post_request')->count();
        $postSuccesses = DeliveryLog::whereDate('created_at', today())
            ->whereNotNull('post_request')
            ->where('status', 'success')
            ->count();

        $postSuccessRate = $postsToday > 0 ? round(($postSuccesses / $postsToday) * 100, 1) : null;

        $status = $this->resolveOverallStatus($checks, $failedJobs);
        $label = $this->statusLabel($status);

        $payload = [
            'status' => $status,
            'label' => $label,
            'checked_at' => now()->toIso8601String(),
            'checks' => $checks,
            'metrics' => [
                'failed_jobs' => $failedJobs,
                'pending_queue' => $pendingQueue,
                'leads_today' => $leadsToday,
                'posts_today' => $postsToday,
                'post_success_rate' => $postSuccessRate,
                'avg_processing_ms' => $this->processingMetrics->avgProcessingMs(),
                'p95_processing_ms' => $this->processingMetrics->p95ProcessingMs(),
                'processing_target_ms' => $this->processingMetrics->targetMs(),
                'processing_on_target' => $this->processingMetrics->withinTarget(),
            ],
            'uptime_30d' => $this->uptimePercent(30),
        ];

        Cache::put(self::CACHE_KEY, $payload, now()->addHours(2));

        app(PlatformAdminAlertService::class)->syncAll($payload);

        if ($persistDaily) {
            PlatformStatusSnapshot::updateOrCreate(
                ['snapshot_date' => today()],
                [
                    'status' => $status,
                    'payload' => $payload,
                    'checked_at' => now(),
                ]
            );
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>|null  $data
     * @return array<string, mixed>
     */
    public function publicPayload(?array $data = null): array
    {
        $data ??= $this->current();

        return [
            'status' => $data['status'],
            'label' => $data['label'],
            'checked_at' => $data['checked_at'],
            'uptime_30d' => $data['uptime_30d'],
            'components' => $this->tenantFacingComponents($data['checks'] ?? []),
            'metrics' => [
                'avg_processing_ms' => $data['metrics']['avg_processing_ms'] ?? null,
                'processing_target_ms' => $data['metrics']['processing_target_ms'] ?? null,
                'processing_on_target' => $data['metrics']['processing_on_target'] ?? null,
                'failed_jobs' => $data['metrics']['failed_jobs'] ?? 0,
                'pending_queue' => $data['metrics']['pending_queue'] ?? 0,
                'post_success_rate' => $data['metrics']['post_success_rate'] ?? null,
            ],
        ];
    }

    /**
     * Customer-facing service list — no internal env/config checks (domains, Horizon, Redis, etc.).
     *
     * @param  list<array{key: string, label: string, status: string, message: string, category?: string}>  $checks
     * @return list<array{key: string, name: string, status: string, message: string}>
     */
    protected function tenantFacingComponents(array $checks): array
    {
        $byKey = collect($checks)->keyBy('key');

        $database = $byKey->get('database');
        $queue = $byKey->get('queue');
        $processing = $byKey->get('processing_speed');
        $postQuality = $byKey->get('post_quality');
        $internalErrors = $byKey->get('internal_errors');

        $apiStatus = $this->worstStatus([
            $database['status'] ?? 'ok',
            $queue['status'] ?? 'ok',
        ]);

        $components = [
            [
                'key' => 'lead_api',
                'name' => 'Lead ingest API',
                'status' => $apiStatus,
                'message' => match ($apiStatus) {
                    'critical' => 'Lead ingest is unavailable — we are investigating',
                    'warning' => 'Lead ingest may be delayed',
                    default => 'Accepting leads via API and webhooks',
                },
            ],
            [
                'key' => 'lead_processing',
                'name' => 'Lead processing',
                'status' => $processing['status'] ?? 'ok',
                'message' => $this->friendlyProcessingMessage($processing),
            ],
            [
                'key' => 'buyer_delivery',
                'name' => 'Buyer delivery',
                'status' => $postQuality['status'] ?? 'ok',
                'message' => $this->friendlyPostMessage($postQuality),
            ],
            [
                'key' => 'platform_reliability',
                'name' => 'Platform reliability',
                'status' => $internalErrors['status'] ?? 'ok',
                'message' => $this->friendlyReliabilityMessage($internalErrors),
            ],
            [
                'key' => 'partner_portals',
                'name' => 'Partner portals',
                'status' => $apiStatus,
                'message' => $apiStatus === 'ok'
                    ? 'Admin, buyer, and supplier portals are available'
                    : 'Some portal features may be unavailable',
            ],
        ];

        return $components;
    }

    /**
     * @param  array{message?: string, status?: string}|null  $check
     */
    protected function friendlyProcessingMessage(?array $check): string
    {
        if (! $check) {
            return 'Validation and routing within target latency';
        }

        $message = (string) ($check['message'] ?? '');

        if (str_contains($message, 'No samples')) {
            return 'Validation and routing within target latency';
        }

        if (preg_match('/Avg ([\d.]+)ms/', $message, $matches)) {
            return "Average lead processing {$matches[1]}ms";
        }

        return 'Lead validation and distribution active';
    }

    /**
     * @param  array{message?: string, status?: string}|null  $check
     */
    protected function friendlyPostMessage(?array $check): string
    {
        if (! $check) {
            return 'Ping-post and direct post delivery to buyers';
        }

        $message = (string) ($check['message'] ?? '');

        if (str_contains($message, 'No posts today')) {
            return 'No buyer posts recorded yet today';
        }

        if (preg_match('/([\d.]+)% success/', $message, $matches)) {
            return "{$matches[1]}% of buyer posts succeeded today";
        }

        if (str_contains($message, 'below')) {
            return 'Monitoring buyer post success — low volume today';
        }

        return 'Ping-post and direct post delivery to buyers';
    }

    /**
     * @param  array{message?: string, status?: string}|null  $check
     */
    protected function friendlyReliabilityMessage(?array $check): string
    {
        if (! $check || ($check['status'] ?? 'ok') === 'ok') {
            return 'No platform delivery errors detected today';
        }

        if (preg_match('/(\d+) platform delivery error/', (string) ($check['message'] ?? ''), $matches)) {
            return "{$matches[1]} delivery error(s) detected today — under investigation";
        }

        return 'Some delivery errors detected today';
    }

    /**
     * @param  list<string>  $statuses
     */
    protected function worstStatus(array $statuses): string
    {
        if (in_array('critical', $statuses, true)) {
            return 'critical';
        }

        if (in_array('warning', $statuses, true)) {
            return 'warning';
        }

        return 'ok';
    }

    /**
     * @param  list<array{status: string}>  $checks
     */
    protected function resolveOverallStatus(array $checks, int $failedJobs): string
    {
        if (collect($checks)->contains(fn (array $check) => $check['status'] === 'critical')) {
            return 'outage';
        }

        if ($failedJobs > 0 || collect($checks)->contains(fn (array $check) => $check['status'] === 'warning')) {
            return 'degraded';
        }

        return 'operational';
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            'outage' => 'Service disruption',
            'degraded' => 'Degraded performance',
            default => 'All systems operational',
        };
    }

    protected function isFresh(array $cached): bool
    {
        if (empty($cached['checked_at'])) {
            return false;
        }

        return now()->diffInMinutes(\Carbon\Carbon::parse($cached['checked_at'])) < 15;
    }

    protected function failedJobsCount(): int
    {
        if (! DB::getSchemaBuilder()->hasTable('failed_jobs')) {
            return 0;
        }

        return (int) DB::table('failed_jobs')->count();
    }

    protected function uptimePercent(int $days): float
    {
        if (! DB::getSchemaBuilder()->hasTable('platform_status_snapshots')) {
            return 100.0;
        }

        $since = today()->subDays($days - 1);
        $snapshots = PlatformStatusSnapshot::query()
            ->whereDate('snapshot_date', '>=', $since)
            ->get();

        if ($snapshots->isEmpty()) {
            return 100.0;
        }

        $operational = $snapshots->where('status', 'operational')->count();

        return round(($operational / $snapshots->count()) * 100, 2);
    }
}
