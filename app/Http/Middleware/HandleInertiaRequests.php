<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Models\Campaign;
use App\Models\User;
use App\Services\Billing\AccountBillingService;
use App\Services\Billing\FraudProtectionService;
use App\Services\Platform\PlatformNotificationService;
use App\Services\Platform\PlatformStatusService;
use App\Support\Admin\TenantHub;
use App\Support\Money;
use App\Support\Tenancy\AccountContext;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user();
        $hostAccount = $request->attributes->get('host_account');

        return [
            ...parent::share($request),
            'tenant' => fn () => $hostAccount instanceof Account ? $this->formatAccount($hostAccount) : null,
            'faviconUrl' => fn () => $this->resolveFaviconUrl($request),
            'isCentralHost' => fn () => TenantResolver::isCentralHost($request->getHost()),
            'auth' => [
                'user' => fn () => $user ? [
                    ...$user->loadMissing(['buyer', 'supplier'])->toArray(),
                    'avatar_url' => $user->avatar_path
                        ? Storage::disk('public')->url($user->avatar_path)
                        : null,
                ] : null,
                // Resolved lazily so route middleware (SetAccountFromUser) runs first.
                'account' => fn () => $this->formatAccount($this->resolveAccount($request)),
                'billing' => fn () => ($account = $this->resolveAccount($request))
                    ? app(AccountBillingService::class)->summary($account)
                    : null,
                'fraudProtection' => fn () => ($account = $this->resolveAccount($request))
                    ? app(FraudProtectionService::class)->summary($account)
                    : null,
                'isSuperAdmin' => fn () => $user?->isSuperAdmin() ?? false,
                'isBuyerPortal' => fn () => $user?->isBuyerPortal() ?? false,
                'isSupplierPortal' => fn () => $user?->isSupplierPortal() ?? false,
                'showLiveStats' => fn () => $this->shouldShowLiveStats($request),
                'selectedTenantId' => fn () => $request->session()->get('current_account_id'),
                'impersonator' => fn () => $request->session()->get('impersonator_id')
                    ? User::find($request->session()->get('impersonator_id'))?->only(['id', 'name', 'email'])
                    : null,
                'godMode' => fn () => (bool) $request->session()->get('god_mode'),
                'allowedModules' => fn () => $user?->resolvedModules() ?? [],
                'preferences' => fn () => $user ? [
                    'theme' => $user->theme ?? 'light',
                    'accent_color' => $user->accent_color ?? 'indigo',
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'demo_success' => fn () => $request->session()->get('demo_success'),
                'api_token' => fn () => $request->session()->get('api_token'),
            ],
            'notifications' => [
                'unread_count' => fn () => $user
                    ? rescue(fn () => app(PlatformNotificationService::class)->unreadCount($user), 0, false)
                    : 0,
            ],
            'urls' => [
                'marketingSignIn' => fn () => $this->marketingSignInUrl($request),
                'centralLogin' => fn () => 'https://'.TenantResolver::baseDomain().'/login',
                'centralAdmin' => fn () => $user?->isSuperAdmin()
                    ? rtrim(TenantResolver::centralUrl(''), '/')
                    : null,
            ],
            'platform' => fn () => $this->platformContext($request),
            'tenantHub' => fn () => TenantHub::forAccount(
                $this->resolveAccount($request),
                $this->resolveCampaignId($request),
            ),
            'platformHub' => fn () => ($user?->isSuperAdmin() && TenantResolver::isCentralHost($request->getHost()))
                ? TenantHub::forCentralAdmin()
                : null,
            'systemStatus' => fn () => $this->systemStatus($request),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function systemStatus(Request $request): ?array
    {
        if (! TenantResolver::isCentralHost($request->getHost())) {
            return null;
        }

        return rescue(
            fn () => app(PlatformStatusService::class)->publicPayload(),
            null,
            false
        );
    }

    /**
     * @return array{currency: string, locale: string}|null
     */
    protected function platformContext(Request $request): ?array
    {
        $account = $this->resolveAccount($request);

        if (! $account) {
            return null;
        }

        $currency = strtoupper($account->default_currency ?? 'GBP');

        return [
            'currency' => $currency,
            'locale' => Money::localeFor($currency),
            'liveStatsInterval' => config('platform.live_stats_interval', 15),
        ];
    }

    protected function marketingSignInUrl(Request $request): string
    {
        $user = $request->user();

        if (! $user) {
            return route('login');
        }

        if (TenantResolver::isCentralHost($request->getHost())) {
            return route('platform.entry');
        }

        if ($user->isBuyerPortal()) {
            return route('portal.buyer.dashboard');
        }

        if ($user->isSupplierPortal()) {
            return route('portal.supplier.dashboard');
        }

        return route('dashboard');
    }

    protected function shouldShowLiveStats(Request $request): bool
    {
        $user = $request->user();

        if (! $user) {
            return false;
        }

        if ($user->isBuyerPortal() || $user->isSupplierPortal()) {
            return false;
        }

        if ($user->isSuperAdmin() && ! $this->resolveAccount($request)) {
            return false;
        }

        return true;
    }

    protected function resolveCampaignId(Request $request): ?int
    {
        $campaign = $request->route('campaign');

        if ($campaign instanceof Campaign) {
            return $campaign->id;
        }

        if (is_numeric($campaign)) {
            return (int) $campaign;
        }

        $campaignId = $request->integer('campaign_id');

        return $campaignId > 0 ? $campaignId : null;
    }

    protected function resolveAccount(Request $request): ?Account
    {
        $user = $request->user();

        if (! $user) {
            return null;
        }

        $account = $request->attributes->get('account');

        if ($account instanceof Account) {
            return $account;
        }

        if ($id = AccountContext::id()) {
            return Account::find($id);
        }

        if ($user?->account_id) {
            return Account::find($user->account_id);
        }

        if ($user?->buyer_id) {
            return $user->buyer?->account;
        }

        if ($user?->supplier_id) {
            return $user->supplier?->account;
        }

        if ($user?->isSuperAdmin() && $request->session()->has('current_account_id')) {
            return Account::find($request->session()->get('current_account_id'));
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function formatAccount(?Account $account): ?array
    {
        if (! $account) {
            return null;
        }

        return [
            ...$account->toArray(),
            'logo_url' => $account->logo_path
                ? Storage::disk('public')->url($account->logo_path)
                : null,
            'favicon_url' => $account->favicon_path
                ? Storage::disk('public')->url($account->favicon_path)
                : null,
            'display_name' => $account->brand_name ?: $account->name,
        ];
    }

    protected function resolveFaviconUrl(Request $request): string
    {
        $hostAccount = $request->attributes->get('host_account');

        if ($hostAccount instanceof Account && $hostAccount->favicon_path) {
            return Storage::disk('public')->url($hostAccount->favicon_path);
        }

        $account = $this->resolveAccount($request);

        if ($account?->favicon_path) {
            return Storage::disk('public')->url($account->favicon_path);
        }

        return asset('favicon.svg');
    }
}
