<?php

namespace App\Services\Platform;

use App\Models\Account;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Support\Facades\DB;

class PlatformOpsCheck
{
    /**
     * @return list<array{key: string, label: string, status: string, message: string, hint: ?string, command: ?string}>
     */
    public function run(): array
    {
        return [
            $this->checkBaseDomain(),
            $this->checkSessionDomain(),
            $this->checkDatabase(),
            $this->checkQueue(),
            $this->checkTenantHosts(),
        ];
    }

    /**
     * @return array{linked: list<string>, missing: list<string>, commands: list<string>, shell_script: string, needs_linking: bool}
     */
    public function herdLinkStatus(): array
    {
        $accounts = Account::where('is_active', true)->orderBy('name')->get();
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
    }

    /**
     * @return array{key: string, label: string, status: string, message: string, hint: ?string, command: ?string}
     */
    protected function checkBaseDomain(): array
    {
        $base = TenantResolver::baseDomain();
        $ok = $base !== '' && $base !== 'localhost';

        return [
            'key' => 'base_domain',
            'label' => 'APP_BASE_DOMAIN',
            'status' => $ok ? 'ok' : 'warning',
            'message' => $ok ? "Base domain is {$base}" : 'Base domain not configured for multi-tenant subdomains',
            'hint' => $ok ? null : 'Set APP_BASE_DOMAIN in .env',
            'command' => null,
        ];
    }

    /**
     * @return array{key: string, label: string, status: string, message: string, hint: ?string, command: ?string}
     */
    protected function checkSessionDomain(): array
    {
        $sessionDomain = config('session.domain');
        $base = TenantResolver::baseDomain();
        $expected = '.'.$base;
        $ok = $sessionDomain === $expected || $sessionDomain === $base;

        return [
            'key' => 'session_domain',
            'label' => 'SESSION_DOMAIN',
            'status' => $ok ? 'ok' : 'warning',
            'message' => $sessionDomain
                ? "Session cookie domain: {$sessionDomain}"
                : 'Session domain not set',
            'hint' => $ok ? null : "Set SESSION_DOMAIN=.{$base} in .env",
            'command' => null,
        ];
    }

    /**
     * @return array{key: string, label: string, status: string, message: string, hint: ?string, command: ?string}
     */
    protected function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return [
                'key' => 'database',
                'label' => 'Database',
                'status' => 'ok',
                'message' => 'Connection healthy',
                'hint' => null,
                'command' => null,
            ];
        } catch (\Throwable $e) {
            return [
                'key' => 'database',
                'label' => 'Database',
                'status' => 'critical',
                'message' => 'Connection failed',
                'hint' => $e->getMessage(),
                'command' => null,
            ];
        }
    }

    /**
     * @return array{key: string, label: string, status: string, message: string, hint: ?string, command: ?string}
     */
    protected function checkQueue(): array
    {
        $driver = config('queue.default');
        $failed = 0;

        if (DB::getSchemaBuilder()->hasTable('failed_jobs')) {
            $failed = (int) DB::table('failed_jobs')->count();
        }

        $storageOk = is_dir(storage_path('app/public')) && is_writable(storage_path('app/public'));

        return [
            'key' => 'queue',
            'label' => 'Queue & storage',
            'status' => $failed > 0 || ! $storageOk ? 'warning' : 'ok',
            'message' => "Driver: {$driver}".($failed > 0 ? " · {$failed} failed" : '').($storageOk ? '' : ' · storage issue'),
            'hint' => $failed > 0 ? 'Run queue:retry all' : ($storageOk ? 'Run queue:work in production' : 'Run storage:link'),
            'command' => $failed > 0 ? 'php artisan queue:retry all' : 'php artisan queue:work',
        ];
    }

    /**
     * @return array{key: string, label: string, status: string, message: string, hint: ?string, command: ?string}
     */
    protected function checkTenantHosts(): array
    {
        $herd = $this->herdLinkStatus();
        $total = count($herd['linked']) + count($herd['missing']);

        if ($total === 0) {
            return [
                'key' => 'tenant_hosts',
                'label' => 'Tenant subdomains',
                'status' => 'warning',
                'message' => 'No active partner platforms',
                'hint' => null,
                'command' => null,
            ];
        }

        if ($herd['needs_linking']) {
            return [
                'key' => 'tenant_hosts',
                'label' => 'Tenant subdomains',
                'status' => 'warning',
                'message' => count($herd['missing']).' of '.$total.' subdomain(s) not resolving locally',
                'hint' => 'Link missing hosts in Laravel Herd (see setup below)',
                'command' => 'php artisan platform:link-tenants',
            ];
        }

        return [
            'key' => 'tenant_hosts',
            'label' => 'Tenant subdomains',
            'status' => 'ok',
            'message' => "All {$total} tenant subdomain(s) resolve",
            'hint' => null,
            'command' => null,
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
