<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Buyer;
use App\Models\User;
use App\Mail\PortalCredentialsMail;
use App\Services\Integrations\BuyerWebhookSync;
use App\Support\Admin\ResolvesAdminAccount;
use App\Support\Tenancy\AccountContext;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BuyerController extends Controller
{
    use ResolvesAdminAccount;

    public function index(Request $request): Response
    {
        $this->resolveAdminAccount($request);

        $query = Buyer::query()
            ->withCount(['deliveries', 'leads'])
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return Inertia::render('Admin/Buyers/Index', [
            'buyers' => $query->paginate(25)->withQueryString()->through(fn (Buyer $buyer) => [
                ...$buyer->toArray(),
                'resolved_currency' => $buyer->resolvedCurrency(),
            ]),
            'filters' => $request->only(['search', 'status']),
            'stats' => [
                'total' => Buyer::count(),
                'active' => Buyer::where('status', 'active')->count(),
                'total_credit' => (float) Buyer::sum('credit_balance'),
            ],
            'currency' => AccountContext::get()?->default_currency ?? 'GBP',
        ]);
    }

    public function show(Request $request, Buyer $buyer): Response
    {
        $this->resolveAdminAccountForTenant($request, $buyer->account_id);

        $buyer->loadCount(['leads', 'deliveries', 'transactions']);

        $recentLeads = $buyer->leads()
            ->with(['campaign:id,name', 'financials'])
            ->orderByDesc('distributed_at')
            ->limit(10)
            ->get();

        $recentTransactions = $buyer->transactions()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        $billing = app(\App\Services\Billing\AccountBillingService::class);
        $portalUser = User::query()
            ->where('buyer_id', $buyer->id)
            ->where('role', UserRole::BuyerPortal)
            ->first(['id', 'email', 'name']);

        return Inertia::render('Admin/Buyers/Show', [
            'buyer' => $buyer->load(['deliveries.campaign']),
            'recentLeads' => $recentLeads,
            'recentTransactions' => $recentTransactions,
            'isOperational' => $billing->isBuyerOperational($buyer),
            'currency' => $buyer->resolvedCurrency(),
            'portalUser' => $portalUser,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->resolveAdminAccount($request);

        return Inertia::render('Admin/Buyers/Form', [
            'buyer' => null,
            'portalUser' => null,
            'currencies' => $this->currencies(),
            'defaultCurrency' => AccountContext::get()?->default_currency ?? 'GBP',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $account = $this->resolveAdminAccount($request);
        AccountContext::set($account);
        $request->attributes->set('account', $account);

        $validated = $this->validateBuyer($request);
        $portal = $this->extractPortalFields($validated);
        $sendCredentials = (bool) ($validated['send_portal_credentials'] ?? false);
        unset($validated['send_portal_credentials']);

        $buyer = Buyer::create($validated);
        app(BuyerWebhookSync::class)->sync($buyer, $validated['settings']['sold_webhook_url'] ?? null);
        $user = $this->syncPortalUser($buyer, $portal, $sendCredentials);

        if ($sendCredentials && $user) {
            return redirect()->route('buyers.show', $buyer)->with('success', 'Buyer created and portal credentials emailed.');
        }

        return redirect()->route('buyers.show', $buyer)->with('success', 'Buyer created.');
    }

    public function edit(Request $request, Buyer $buyer): Response
    {
        $this->resolveAdminAccountForTenant($request, $buyer->account_id);

        $portalUser = User::query()
            ->where('buyer_id', $buyer->id)
            ->where('role', UserRole::BuyerPortal)
            ->first(['id', 'email', 'name']);

        $buyerPayload = $buyer->toArray();
        $soldWebhookUrl = app(BuyerWebhookSync::class)->urlForBuyer($buyer);
        if ($soldWebhookUrl) {
            $buyerPayload['settings'] = array_merge($buyerPayload['settings'] ?? [], [
                'sold_webhook_url' => $soldWebhookUrl,
            ]);
        }

        return Inertia::render('Admin/Buyers/Form', [
            'buyer' => $buyerPayload,
            'portalUser' => $portalUser,
            'currencies' => $this->currencies(),
            'defaultCurrency' => $buyer->account?->default_currency ?? 'GBP',
        ]);
    }

    public function update(Request $request, Buyer $buyer): RedirectResponse
    {
        $this->resolveAdminAccountForTenant($request, $buyer->account_id);

        $validated = $this->validateBuyer($request, $buyer);
        $portal = $this->extractPortalFields($validated);
        $sendCredentials = (bool) ($validated['send_portal_credentials'] ?? false);
        unset($validated['send_portal_credentials']);

        $buyer->update($validated);
        app(BuyerWebhookSync::class)->sync($buyer, $validated['settings']['sold_webhook_url'] ?? null);
        $user = $this->syncPortalUser($buyer, $portal, $sendCredentials);

        if ($sendCredentials && $user) {
            return redirect()->route('buyers.show', $buyer)->with('success', 'Buyer updated and portal credentials emailed.');
        }

        return redirect()->route('buyers.show', $buyer)->with('success', 'Buyer updated.');
    }

    public function destroy(Request $request, Buyer $buyer): RedirectResponse
    {
        $this->resolveAdminAccountForTenant($request, $buyer->account_id);

        $buyer->delete();

        return redirect()->route('buyers.index')->with('success', 'Buyer deleted.');
    }

    protected function validateBuyer(Request $request, ?Buyer $buyer = null): array
    {
        $request->merge([
            'reference' => strtolower(trim((string) $request->input('reference', ''))),
        ]);

        $accountId = AccountContext::id()
            ?? $buyer?->account_id
            ?? $this->resolveOptionalAdminAccount($request)?->id;

        $validated = $request->validate([
            'reference' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('buyers', 'reference')
                    ->where(fn ($q) => $q->where('account_id', $accountId))
                    ->ignore($buyer?->id),
            ],
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'status' => 'nullable|in:active,inactive',
            'credit_balance' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'caps' => 'nullable|array',
            'caps.daily' => 'nullable|integer|min:0',
            'caps.hourly' => 'nullable|integer|min:0',
            'caps.monthly' => 'nullable|integer|min:0',
            'caps.daily_spend_cap' => 'nullable|numeric|min:0',
            'caps.monthly_spend_cap' => 'nullable|numeric|min:0',
            'schedule' => 'nullable|array',
            'schedule.enabled' => 'nullable|boolean',
            'schedule.timezone' => 'nullable|string|max:64',
            'schedule.start' => 'nullable|string|max:5',
            'schedule.end' => 'nullable|string|max:5',
            'settings' => 'nullable|array',
            'settings.exclusive_only' => 'nullable|boolean',
            'settings.min_quality_score' => 'nullable|integer|min:0|max:100',
            'settings.duplicate_window_hours' => 'nullable|integer|min:0|max:720',
            'settings.auto_topup_threshold' => 'nullable|numeric|min:0',
            'settings.auto_topup_amount' => 'nullable|numeric|min:0',
            'settings.pricing_model' => 'nullable|in:cpl,cpc,cpf,rev_share',
            'settings.default_cpc_override' => 'nullable|numeric|min:0',
            'settings.low_credit_alert' => 'nullable|numeric|min:0',
            'settings.conversion_postback_url' => 'nullable|url|max:500',
            'settings.sold_webhook_url' => 'nullable|url|max:500',
            'settings.notify_on_sale' => 'nullable|boolean',
            'settings.geo_countries' => 'nullable|array',
            'settings.geo_countries.*' => 'string|size:2',
            'portal_email' => 'nullable|email|max:255',
            'portal_password' => 'nullable|string|min:8|max:255',
            'portal_name' => 'nullable|string|max:255',
            'send_portal_credentials' => 'nullable|boolean',
            'generate_portal_password' => 'nullable|boolean',
        ], [
            'reference.required' => 'Buyer reference is required.',
            'reference.regex' => 'Reference may only contain letters, numbers, hyphens and underscores.',
            'reference.unique' => 'This buyer reference already exists on your platform.',
            'name.required' => 'Buyer name is required.',
            'email.email' => 'Enter a valid email address.',
            'credit_balance.min' => 'Credit balance cannot be negative.',
            'portal_password.min' => 'Portal password must be at least 8 characters.',
        ]);

        $validated['status'] = $validated['status'] ?? 'active';
        $validated['currency'] = strtoupper($validated['currency'] ?? AccountContext::get()?->default_currency ?? 'GBP');
        $validated['caps'] = array_filter($validated['caps'] ?? [], fn ($v) => $v !== null && $v !== '');
        $validated['schedule'] = $validated['schedule'] ?? null;
        $validated['settings'] = array_filter($validated['settings'] ?? [], fn ($v) => $v !== null && $v !== '');

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{email: ?string, password: ?string, name: ?string}
     */
    protected function extractPortalFields(array &$validated): array
    {
        $portal = [
            'email' => $validated['portal_email'] ?? null,
            'password' => $validated['portal_password'] ?? null,
            'name' => $validated['portal_name'] ?? null,
            'generate' => (bool) ($validated['generate_portal_password'] ?? false),
        ];

        unset($validated['portal_email'], $validated['portal_password'], $validated['portal_name'], $validated['generate_portal_password']);

        return $portal;
    }

    /**
     * @param  array{email: ?string, password: ?string, name: ?string}  $portal
     */
    protected function syncPortalUser(Buyer $buyer, array $portal, bool $sendCredentials = false): ?User
    {
        if (empty($portal['email'])) {
            return null;
        }

        $user = User::query()
            ->where('buyer_id', $buyer->id)
            ->where('role', UserRole::BuyerPortal)
            ->first();

        $plainPassword = null;

        if (! empty($portal['password'])) {
            $plainPassword = $portal['password'];
        } elseif ($sendCredentials || ($portal['generate'] ?? false)) {
            $plainPassword = Str::password(12);
        }

        $data = [
            'account_id' => $buyer->account_id,
            'buyer_id' => $buyer->id,
            'email' => $portal['email'],
            'name' => $portal['name'] ?: $buyer->name.' Portal',
            'role' => UserRole::BuyerPortal,
        ];

        if ($plainPassword) {
            $data['password'] = $plainPassword;
        }

        if ($user) {
            $user->update(array_filter($data, fn ($v) => $v !== null));
        } else {
            $data['password'] = $data['password'] ?? ($plainPassword ?? 'password');
            $user = User::create($data);
            $plainPassword = $plainPassword ?? 'password';
        }

        if ($sendCredentials && $plainPassword) {
            $this->mailPortalCredentials($buyer, $user, $plainPassword);
        }

        return $user;
    }

    protected function mailPortalCredentials(Buyer $buyer, User $user, string $plainPassword): void
    {
        $account = $buyer->account;
        $platformName = $account?->brand_name ?: $account?->name ?: 'PowerByExcellence';
        $portalUrl = $account
            ? TenantResolver::portalUrl($account, '/login')
            : url('/login');

        try {
            Mail::to($user->email)->send(new PortalCredentialsMail($user, $plainPassword, $portalUrl, $platformName));
        } catch (\Throwable) {
            // Non-blocking
        }
    }

    /**
     * @return list<string>
     */
    protected function currencies(): array
    {
        return ['GBP', 'USD', 'EUR', 'CAD', 'AUD', 'NZD', 'ZAR', 'INR', 'AED'];
    }
}
