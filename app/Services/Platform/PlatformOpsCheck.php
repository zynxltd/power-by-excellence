<?php

namespace App\Services\Platform;

use App\Models\Account;
use App\Models\DeliveryLog;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class PlatformOpsCheck
{
    public const CHECKS_CACHE_KEY = 'platform.ops.checks.v1';

    public const HERD_CACHE_KEY = 'platform.ops.herd.v1';

    public function __construct(
        protected ProcessingMetrics $processingMetrics,
    ) {}

    /**
     * @return list<array{key: string, label: string, status: string, message: string, hint: ?string, command: ?string, category: string}>
     */
    public function run(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::CHECKS_CACHE_KEY);
            Cache::forget(self::HERD_CACHE_KEY);
        }

        return Cache::remember(self::CHECKS_CACHE_KEY, 60, fn () => $this->compileChecks());
    }

    /**
     * @return list<array{key: string, label: string, status: string, message: string, hint: ?string, command: ?string, category: string}>
     */
    protected function compileChecks(): array
    {
        $herd = $this->herdLinkStatus();

        return [
            $this->checkBaseDomain(),
            $this->checkSessionDomain(),
            $this->checkDatabase(),
            $this->checkCache(),
            $this->checkRedis(),
            $this->checkQueue(),
            $this->checkStorage(),
            $this->checkScheduler(),
            $this->checkHorizon(),
            $this->checkTenantHosts($herd),
            $this->checkProcessingSpeed(),
            $this->checkPostQuality(),
            $this->checkInternalErrors(),
        ];
    }

    /**
     * @return array{linked: list<string>, missing: list<string>, commands: list<string>, shell_script: string, needs_linking: bool}
     */
    public function herdLinkStatus(bool $fresh = false): array
    {
        if ($fresh) {
            Cache::forget(self::HERD_CACHE_KEY);
        }

        return Cache::remember(self::HERD_CACHE_KEY, 300, function () {
            $accounts = Account::where('is_active', true)->orderBy('name')->get(['id', 'name', 'slug', 'domain']);
            $linked = [];
            $missing = [];
            $commands = [];

            foreach ($accounts as $account) {
                $host = TenantResolver::portalHost($account);
                $commands[] = "herd link {$host}";

                if ($this->hostResolves($host)) {
                    $linked[] = $host;
                } else {
                    $missing[] = $host;
                }
            }

            return [
                'linked' => $linked,
                'missing' => $missing,
                'commands' => $commands,
                'shell_script' => implode("\n", $commands),
                'needs_linking' => count($missing) > 0,
            ];
        });
    }

    /**
     * @return array{key: string, label: string, status: string, message: string, hint: ?string, command: ?string, category: string}
     */
    protected function checkBaseDomain(): array
    {
        $base = TenantResolver::baseDomain();
        $ok = $base !== '' && $base !== 'localhost';

        return $this->result(
            'base_domain',
            'APP_BASE_DOMAIN',
            $ok ? 'ok' : 'warning',
            $ok ? "Base domain is {$base}" : 'Base domain not configured for multi-tenant subdomains',
            $ok ? null : 'Set APP_BASE_DOMAIN in .env',
            null,
            'infrastructure',
        );
    }

    /**
     * @return array{key: string, label: string, status: string, message: string, hint: ?string, command: ?string, category: string}
     */
    protected function checkSessionDomain(): array
    {
        $sessionDomain = config('session.domain');
        $base = TenantResolver::baseDomain();
        $expected = '.'.$base;
        $ok = $sessionDomain === $expected || $sessionDomain === $base;

        return $this->result(
            'session_domain',
            'SESSION_DOMAIN',
            $ok ? 'ok' : 'warning',
            $sessionDomain
                ? "Session cookie domain: {$sessionDomain}"
                : 'Session domain not set',
            $ok ? null : "Set SESSION_DOMAIN=.{$base} in .env",
            null,
            'infrastructure',
        );
    }

    /**
     * @return array{key: string, label: string, status: string, message: string, hint: ?string, command: ?string, category: string}
     */
    protected function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            DB::select('select 1');
            $ms = round((microtime(true) - $start) * 1000);

            return $this->result(
                'database',
                'Database',
                'ok',
                "Connection healthy · {$ms}ms ping",
                null,
                null,
                'infrastructure',
            );
        } catch (\Throwable $e) {
            return $this->result(
                'database',
                'Database',
                'critical',
                'Connection failed',
                $e->getMessage(),
                null,
                'infrastructure',
            );
        }
    }

    /**
     * @return array{key: string, label: string, status: string, message: string, hint: ?string, command: ?string, category: string}
     */
    protected function checkCache(): array
    {
        $driver = config('cache.default');

        try {
            $key = 'platform.ops.cache_ping';
            Cache::put($key, 'ok', 10);
            $value = Cache::get($key);
            Cache::forget($key);

            if ($value !== 'ok') {
                return $this->result(
                    'cache',
                    'Cache',
                    'warning',
                    "Driver {$driver} — read/write mismatch",
                    'Verify cache store configuration',
                    null,
                    'infrastructure',
                );
            }

            return $this->result(
                'cache',
                'Cache',
                'ok',
                "Driver {$driver} — read/write OK",
                null,
                null,
                'infrastructure',
            );
        } catch (\Throwable $e) {
            return $this->result(
                'cache',
                'Cache',
                'critical',
                "Driver {$driver} — unavailable",
                $e->getMessage(),
                null,
                'infrastructure',
            );
        }
    }

    /**
     * @return array{key: string, label: string, status: string, message: string, hint: ?string, command: ?string, category: string}
     */
    protected function checkRedis(): array
    {
        if (config('platform.queue.fallback_active')) {
            return $this->result(
                'redis',
                'Redis',
                'warning',
                'Unavailable — database queue fallback active',
                'Start Redis for Horizon throughput, or keep scheduler running for database queue',
                null,
                'infrastructure',
            );
        }

        $usesRedis = $this->queueExpectsRedis();

        if (! $usesRedis) {
            return $this->result(
                'redis',
                'Redis',
                'ok',
                'Not required (queue/cache not on redis)',
                null,
                null,
                'infrastructure',
            );
        }

        try {
            $connection = Redis::connection();
            $pong = $connection->ping();
            $payload = is_string($pong) ? $pong : 'PONG';

            return $this->result(
                'redis',
                'Redis',
                strtoupper((string) $payload) === 'PONG' || $pong === true ? 'ok' : 'warning',
                'Ping successful — Horizon & redis queues',
                null,
                null,
                'infrastructure',
            );
        } catch (\Throwable $e) {
            return $this->result(
                'redis',
                'Redis',
                'critical',
                'Connection failed',
                $e->getMessage(),
                'Start Redis, or enable QUEUE_REDIS_FALLBACK=true for database queue',
                'infrastructure',
            );
        }
    }

    protected function queueExpectsRedis(): bool
    {
        $preferred = (string) config('platform.queue.preferred_connection', config('queue.default'));

        return in_array($preferred, ['redis', 'failover'], true);
    }

    protected function horizonInstalled(): bool
    {
        return class_exists(\Laravel\Horizon\Horizon::class);
    }

    protected function isHorizonRunning(): bool
    {
        if (! $this->horizonInstalled() || config('queue.default') !== 'redis') {
            return false;
        }

        try {
            $masters = app(\Laravel\Horizon\Contracts\MasterSupervisorRepository::class)->all();

            return ! empty($masters);
        } catch (\Throwable) {
            return false;
        }
    }

    protected function recommendedQueueCommand(): string
    {
        if (config('queue.default') === 'redis' && $this->horizonInstalled()) {
            return 'php artisan horizon';
        }

        if (config('queue.default') === 'database') {
            return 'php artisan schedule:work';
        }

        return 'php artisan queue:work';
    }

    /**
     * @return array{key: string, label: string, status: string, message: string, hint: ?string, command: ?string, category: string}
     */
    protected function checkQueue(): array
    {
        $driver = config('queue.default');
        $failed = 0;

        if (DB::getSchemaBuilder()->hasTable('failed_jobs')) {
            $failed = (int) DB::table('failed_jobs')->count();
        }

        $queueCommand = $this->recommendedQueueCommand();

        if ($driver === 'sync' && app()->environment('production')) {
            return $this->result(
                'queue',
                'Queue worker',
                'warning',
                'Sync driver in production — leads process inline only',
                'Use redis + Horizon or database queue with scheduler',
                $queueCommand,
                'infrastructure',
            );
        }

        if ($driver === 'redis' && $this->horizonInstalled()) {
            $horizonRunning = $this->isHorizonRunning();

            return $this->result(
                'queue',
                'Horizon',
                $failed > 0 ? 'warning' : ($horizonRunning ? 'ok' : 'warning'),
                ($horizonRunning ? 'Horizon running' : 'Horizon inactive')." · driver: {$driver}"
                    .($failed > 0 ? " · {$failed} failed job(s)" : ''),
                $horizonRunning
                    ? ($failed > 0 ? 'Retry or flush failed jobs' : 'Supervise with php artisan horizon in production')
                    : 'Start Horizon to process async leads',
                $failed > 0 ? 'php artisan queue:retry all' : $queueCommand,
                'infrastructure',
            );
        }

        if ($driver === 'database') {
            $fallbackNote = config('platform.queue.fallback_active')
                ? ' · Redis fallback (scheduler drains queue each minute)'
                : ' · scheduler runs queue:work each minute';

            return $this->result(
                'queue',
                'Queue worker',
                $failed > 0 ? 'warning' : 'ok',
                "Driver: {$driver}{$fallbackNote}".($failed > 0 ? " · {$failed} failed job(s)" : ''),
                'Run schedule:work locally, or cron * * * * * php artisan schedule:run in production',
                $failed > 0 ? 'php artisan queue:retry all' : $queueCommand,
                'infrastructure',
            );
        }

        return $this->result(
            'queue',
            'Queue worker',
            $failed > 0 ? 'warning' : 'ok',
            "Driver: {$driver}".($failed > 0 ? " · {$failed} failed job(s)" : ''),
            $failed > 0 ? 'Retry or flush failed jobs' : 'Ensure a worker is running in production',
            $failed > 0 ? 'php artisan queue:retry all' : $queueCommand,
            'infrastructure',
        );
    }

    /**
     * @return array{key: string, label: string, status: string, message: string, hint: ?string, command: ?string, category: string}
     */
    protected function checkStorage(): array
    {
        $publicOk = is_dir(storage_path('app/public')) && is_writable(storage_path('app/public'));
        $linkOk = is_link(public_path('storage')) || is_dir(public_path('storage'));

        if (! $publicOk) {
            return $this->result(
                'storage',
                'Storage',
                'warning',
                'storage/app/public not writable',
                'Fix filesystem permissions',
                null,
                'infrastructure',
            );
        }

        if (! $linkOk) {
            return $this->result(
                'storage',
                'Storage',
                'warning',
                'public/storage symlink missing',
                'Branding uploads and public assets need storage:link',
                'php artisan storage:link',
                'infrastructure',
            );
        }

        return $this->result(
            'storage',
            'Storage',
            'ok',
            'Public disk writable · symlink OK',
            null,
            null,
            'infrastructure',
        );
    }

    /**
     * @return array{key: string, label: string, status: string, message: string, hint: ?string, command: ?string, category: string}
     */
    protected function checkScheduler(): array
    {
        if (app()->environment('local', 'testing')) {
            return $this->result(
                'scheduler',
                'Scheduler',
                'ok',
                'Local environment — cron not required',
                null,
                null,
                'infrastructure',
            );
        }

        $cached = Cache::get(PlatformStatusService::CACHE_KEY);
        $checkedAt = is_array($cached) ? ($cached['checked_at'] ?? null) : null;
        $staleMinutes = (int) config('performance.scheduler_stale_minutes', 20);

        if (! $checkedAt) {
            return $this->result(
                'scheduler',
                'Scheduler',
                'warning',
                'No status snapshot yet',
                'Add cron: * * * * * php artisan schedule:run',
                'php artisan schedule:work',
                'infrastructure',
            );
        }

        $age = now()->diffInMinutes(\Carbon\Carbon::parse($checkedAt));

        if ($age > $staleMinutes) {
            return $this->result(
                'scheduler',
                'Scheduler',
                'warning',
                "Last status refresh {$age}m ago (target ≤{$staleMinutes}m)",
                'Cron must run schedule:run every minute',
                'php artisan schedule:work',
                'infrastructure',
            );
        }

        return $this->result(
            'scheduler',
            'Scheduler',
            'ok',
            "Status refreshed {$age}m ago",
            null,
            null,
            'infrastructure',
        );
    }

    /**
     * @return array{key: string, label: string, status: string, message: string, hint: ?string, command: ?string, category: string}
     */
    protected function checkHorizon(): array
    {
        if (config('queue.default') !== 'redis') {
            return $this->result(
                'horizon',
                'Horizon',
                'ok',
                config('platform.queue.fallback_active')
                    ? 'Using database queue fallback — Horizon not required'
                    : 'Not required (queue not on redis)',
                null,
                null,
                'infrastructure',
            );
        }

        if (! $this->horizonInstalled()) {
            return $this->result(
                'horizon',
                'Horizon',
                'ok',
                'Not installed — use queue:work',
                null,
                'php artisan queue:work',
                'infrastructure',
            );
        }

        if ($this->isHorizonRunning()) {
            return $this->result(
                'horizon',
                'Horizon',
                'ok',
                'Running — supervises redis queue workers',
                null,
                'php artisan horizon',
                'infrastructure',
            );
        }

        if (app()->environment('local', 'testing')) {
            return $this->result(
                'horizon',
                'Horizon',
                'warning',
                'Inactive — start Horizon to process async leads',
                'composer run dev includes Horizon, or run php artisan horizon',
                'php artisan horizon',
                'infrastructure',
            );
        }

        return $this->result(
            'horizon',
            'Horizon',
            'warning',
            'Inactive — production redis queue needs a supervised Horizon process',
            'Supervisor should keep horizon running',
            'php artisan horizon',
            'infrastructure',
        );
    }

    /**
     * @param  array{linked: list<string>, missing: list<string>, needs_linking: bool}  $herd
     * @return array{key: string, label: string, status: string, message: string, hint: ?string, command: ?string, category: string}
     */
    protected function checkTenantHosts(array $herd): array
    {
        $total = count($herd['linked']) + count($herd['missing']);

        if ($total === 0) {
            return $this->result(
                'tenant_hosts',
                'Tenant subdomains',
                'warning',
                'No active partner platforms',
                null,
                null,
                'infrastructure',
            );
        }

        if ($herd['needs_linking']) {
            return $this->result(
                'tenant_hosts',
                'Tenant subdomains',
                'warning',
                count($herd['missing']).' of '.$total.' subdomain(s) not resolving locally',
                'Link missing hosts in Laravel Herd or run platform:link-tenants',
                'php artisan platform:link-tenants',
                'infrastructure',
            );
        }

        return $this->result(
            'tenant_hosts',
            'Tenant subdomains',
            'ok',
            "All {$total} tenant subdomain(s) resolve",
            null,
            null,
            'infrastructure',
        );
    }

    /**
     * @return array{key: string, label: string, status: string, message: string, hint: ?string, command: ?string, category: string}
     */
    protected function checkProcessingSpeed(): array
    {
        $target = $this->processingMetrics->targetMs();
        $avg = $this->processingMetrics->avgProcessingMs();
        $p95 = $this->processingMetrics->p95ProcessingMs();
        $p95Limit = (int) round($target * (float) config('performance.p95_warning_factor', 1.5));

        if ($avg === 0.0 && $p95 === 0.0) {
            return $this->result(
                'processing_speed',
                'Lead processing',
                'ok',
                "No samples (24h) · target <{$target}ms",
                null,
                null,
                'speed',
            );
        }

        $status = 'ok';
        if ($avg > $target || $p95 > $p95Limit) {
            $status = $avg > $target * 2 ? 'critical' : 'warning';
        }

        return $this->result(
            'processing_speed',
            'Lead processing',
            $status,
            "Avg {$avg}ms · P95 {$p95}ms (target <{$target}ms)",
            $status === 'ok' ? null : 'Review slow campaigns, buyer timeouts, and queue depth',
            null,
            'speed',
        );
    }

    /**
     * @return array{key: string, label: string, status: string, message: string, hint: ?string, command: ?string, category: string}
     */
    protected function checkPostQuality(): array
    {
        $posts = DeliveryLog::whereDate('created_at', today())
            ->whereNotNull('post_request')
            ->count();

        $minPosts = (int) config('performance.post_success_rate_min_posts', 5);
        $target = (float) config('performance.post_success_rate_target', 95);

        if ($posts < $minPosts) {
            return $this->result(
                'post_quality',
                'Post success rate',
                'ok',
                $posts === 0 ? 'No posts today yet' : "{$posts} posts today (below {$minPosts} min for gate)",
                null,
                null,
                'quality',
            );
        }

        $successes = DeliveryLog::whereDate('created_at', today())
            ->whereNotNull('post_request')
            ->where('status', 'success')
            ->count();

        $rate = round(($successes / $posts) * 100, 1);

        $status = 'ok';
        if ($rate < $target) {
            $status = $rate < max(80, $target - 10) ? 'critical' : 'warning';
        }

        return $this->result(
            'post_quality',
            'Post success rate',
            $status,
            "{$rate}% today ({$successes}/{$posts}) · target ≥{$target}%",
            $status === 'ok' ? null : 'Check delivery logs for buyer rejects vs platform errors',
            null,
            'quality',
        );
    }

    /**
     * @return array{key: string, label: string, status: string, message: string, hint: ?string, command: ?string, category: string}
     */
    protected function checkInternalErrors(): array
    {
        $internal = (int) \App\Support\Delivery\DeliveryLogClassifier::scopeInternalFailures(
            DeliveryLog::query()->whereDate('delivery_logs.created_at', today())
        )->count();

        if ($internal === 0) {
            return $this->result(
                'internal_errors',
                'Platform errors',
                'ok',
                '0 delivery errors today',
                null,
                null,
                'quality',
            );
        }

        return $this->result(
            'internal_errors',
            'Platform errors',
            $internal >= 5 ? 'critical' : 'warning',
            "{$internal} platform delivery error(s) today",
            'Config, timeout, or exception — not buyer rejections',
            null,
            'quality',
        );
    }

    /**
     * @return array{key: string, label: string, status: string, message: string, hint: ?string, command: ?string, category: string}
     */
    protected function result(
        string $key,
        string $label,
        string $status,
        string $message,
        ?string $hint,
        ?string $command,
        string $category,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $status,
            'message' => $message,
            'hint' => $hint,
            'command' => $command,
            'category' => $category,
        ];
    }

    protected function hostResolves(string $host): bool
    {
        if ($host === '' || in_array($host, ['localhost', '127.0.0.1'], true)) {
            return true;
        }

        $resolved = gethostbyname($host);

        return $resolved !== $host && filter_var($resolved, FILTER_VALIDATE_IP);
    }
}
