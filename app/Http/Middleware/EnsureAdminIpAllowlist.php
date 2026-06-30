<?php

namespace App\Http\Middleware;

use App\Models\AccessLog;
use App\Models\Account;
use App\Models\User;
use App\Services\Security\AdminIpAllowlistPolicy;
use App\Services\Security\AdminIpAllowlistService;
use App\Support\Tenancy\AccountContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminIpAllowlist
{
    public function __construct(
        protected AdminIpAllowlistService $allowlist,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->allowlist->isBypassed()) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        $account = $this->resolveAccount($request, $user);
        if (! $account instanceof Account) {
            return $next($request);
        }

        $policy = AdminIpAllowlistPolicy::forAccount($account);
        if (! $policy['admin_ip_allowlist_enabled']) {
            return $next($request);
        }

        $clientIp = $request->ip();
        if ($this->allowlist->allows($clientIp, $policy['admin_ip_allowlist'])) {
            return $next($request);
        }

        $this->recordBlockedAttempt($account, $user, $request, $clientIp);

        if ($request->expectsJson()) {
            return response()->json(['error' => 'Access denied from this IP address.'], 403);
        }

        abort(403, 'Access denied from this IP address.');
    }

    protected function resolveAccount(Request $request, User $user): ?Account
    {
        $account = AccountContext::get()
            ?? $request->attributes->get('account')
            ?? $user->resolveAccount();

        return $account instanceof Account ? $account : null;
    }

    protected function recordBlockedAttempt(Account $account, User $user, Request $request, ?string $clientIp): void
    {
        try {
            AccessLog::withoutGlobalScopes()->create([
                'account_id' => $account->id,
                'user_id' => $user->id,
                'action' => 'admin_ip_blocked',
                'ip_address' => $clientIp,
                'user_agent' => $request->userAgent(),
                'path' => $request->path(),
            ]);
        } catch (\Throwable) {
            // Never break the request for logging failures.
        }
    }
}
