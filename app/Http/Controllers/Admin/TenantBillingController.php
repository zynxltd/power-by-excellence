<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\Billing\AccountBillingService;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TenantBillingController extends Controller
{
    public function index(Request $request, AccountBillingService $billing): Response
    {
        $this->authorizeSuperAdminCentral($request);

        $accounts = Account::orderBy('name')
            ->get()
            ->map(fn (Account $account) => $this->billingRow($account, $billing));

        $collection = collect($accounts);

        return Inertia::render('Admin/Accounts/Billing/Index', [
            'accounts' => $accounts,
            'currentAccountId' => session('current_account_id'),
            'summary' => [
                'total' => $collection->count(),
                'active' => $collection->where('status', AccountBillingService::STATUS_ACTIVE)->count(),
                'past_due' => $collection->where('status', AccountBillingService::STATUS_PAST_DUE)->count(),
                'locked' => $collection->where('status', AccountBillingService::STATUS_LOCKED)->count(),
                'total_mrr' => round($collection->sum(fn ($row) => (float) ($row['effective_monthly'] ?? 0)), 2),
                'currency' => $collection->first()['currency'] ?? 'GBP',
            ],
        ]);
    }

    public function edit(Request $request, Account $account, AccountBillingService $billing): Response
    {
        $this->authorizeSuperAdminCentral($request);

        return Inertia::render('Admin/Accounts/Billing/Edit', [
            'account' => $this->billingForm($account, $billing),
        ]);
    }

    public function update(Request $request, Account $account, AccountBillingService $billing): RedirectResponse
    {
        $this->authorizeSuperAdminCentral($request);

        $validated = $request->validate([
            'monthly_rent' => 'nullable|numeric|min:0',
            'contract_reference' => 'nullable|string|max:120',
            'billing_status' => 'required|in:active,past_due,locked',
            'billing_lock_reason' => 'nullable|string|max:500',
            'billing_notes' => 'nullable|string|max:2000',
            'billing_alert_emails' => 'nullable|string|max:500',
            'subscription_plan' => 'required|in:starter,growth,enterprise',
        ]);

        $settings = $account->settings ?? [];
        $settings = app(\App\Services\Billing\FraudProtectionService::class)->provisionSettingsForPlan(
            $settings,
            $validated['subscription_plan'],
        );
        $settings['monthly_rent'] = $validated['monthly_rent'] ?? null;
        $settings['contract_reference'] = $validated['contract_reference'] ?? null;
        $settings['billing_alert_emails'] = $validated['billing_alert_emails'] ?? '';
        $settings['billing_notes'] = $validated['billing_notes'] ?? null;

        if ($validated['billing_status'] === 'locked') {
            $reason = $validated['billing_lock_reason'] ?? 'Platform rent overdue or contract suspended.';
            $settings['billing_lock_reason'] = $reason;
            $account->update(['settings' => $settings]);
            $billing->lock($account, $reason);
        } else {
            $settings['billing_status'] = $validated['billing_status'];
            unset($settings['billing_locked_at'], $settings['billing_lock_reason']);
            $account->update([
                'settings' => $settings,
                'is_active' => true,
            ]);
        }

        return redirect()
            ->route('accounts.billing.edit', $account)
            ->with('success', 'Tenant billing updated.');
    }

    public function lock(Request $request, Account $account, AccountBillingService $billing): RedirectResponse
    {
        $this->authorizeSuperAdminCentral($request);

        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $billing->lock($account, $validated['reason'] ?? 'Platform rent suspended by operator.');

        return back()->with('success', "{$account->brand_name} billing locked.");
    }

    public function unlock(Request $request, Account $account, AccountBillingService $billing): RedirectResponse
    {
        $this->authorizeSuperAdminCentral($request);

        $billing->unlock($account);

        return back()->with('success', "{$account->brand_name} billing unlocked.");
    }

    protected function authorizeSuperAdminCentral(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
        abort_unless(\App\Support\Tenancy\TenantResolver::isCentralHost($request->getHost()), 403);
    }

    /**
     * @return array<string, mixed>
     */
    protected function billingRow(Account $account, AccountBillingService $billing): array
    {
        $settings = $account->settings ?? [];
        $summary = $billing->summary($account);
        $branding = $account->publicBranding();

        return [
            'id' => $account->id,
            'name' => $branding['display_name'] ?: $account->name,
            'slug' => $account->slug,
            'domain' => $account->resolvedDomain(),
            'logo_url' => $branding['logo_url'],
            'portal_url' => TenantResolver::portalUrl($account, '/dashboard'),
            'status' => $summary['status'],
            'monthly_rent' => $settings['monthly_rent'] ?? null,
            'effective_monthly' => $this->effectiveMonthly($settings),
            'contract_reference' => $settings['contract_reference'] ?? null,
            'currency' => $account->default_currency,
            'is_active' => $account->is_active,
            'can_accept_leads' => $summary['can_accept_leads'],
            'can_process_leads' => $summary['can_process_leads'],
            'subscription_plan' => $settings['subscription_plan'] ?? 'starter',
            'fraud_protection' => app(\App\Services\Billing\FraudProtectionService::class)->summary($account),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function billingForm(Account $account, AccountBillingService $billing): array
    {
        $settings = $account->settings ?? [];
        $summary = $billing->summary($account);
        $branding = $account->publicBranding();

        return [
            'id' => $account->id,
            'name' => $branding['display_name'] ?: $account->name,
            'slug' => $account->slug,
            'domain' => $account->resolvedDomain(),
            'logo_url' => $branding['logo_url'],
            'portal_url' => TenantResolver::portalUrl($account, '/dashboard'),
            'currency' => $account->default_currency,
            'status' => $summary['status'],
            'locked_at' => $summary['locked_at'],
            'lock_reason' => $summary['lock_reason'],
            'monthly_rent' => $settings['monthly_rent'] ?? '',
            'effective_monthly' => $this->effectiveMonthly($settings),
            'contract_reference' => $settings['contract_reference'] ?? '',
            'billing_status' => $summary['status'],
            'billing_lock_reason' => $settings['billing_lock_reason'] ?? '',
            'billing_notes' => $settings['billing_notes'] ?? '',
            'billing_alert_emails' => $settings['billing_alert_emails'] ?? '',
            'can_accept_leads' => $summary['can_accept_leads'],
            'can_process_leads' => $summary['can_process_leads'],
            'subscription_plan' => $settings['subscription_plan'] ?? 'starter',
            'fraud_protection' => app(\App\Services\Billing\FraudProtectionService::class)->summary($account),
        ];
    }

    protected function effectiveMonthly(array $settings): ?float
    {
        if (! isset($settings['monthly_rent']) || $settings['monthly_rent'] === '') {
            return null;
        }

        return round((float) $settings['monthly_rent'], 2);
    }
}
