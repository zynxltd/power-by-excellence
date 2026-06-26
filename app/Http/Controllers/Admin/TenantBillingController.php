<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\Billing\AccountBillingService;
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

        return Inertia::render('Admin/Accounts/Billing/Index', [
            'accounts' => $accounts,
            'currentAccountId' => session('current_account_id'),
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
            'billing_due_at' => 'nullable|date',
            'billing_status' => 'required|in:active,past_due,locked',
            'billing_lock_reason' => 'nullable|string|max:500',
            'billing_notes' => 'nullable|string|max:2000',
            'billing_alert_emails' => 'nullable|string|max:500',
        ]);

        $settings = $account->settings ?? [];
        $settings['monthly_rent'] = $validated['monthly_rent'] ?? null;
        $settings['contract_reference'] = $validated['contract_reference'] ?? null;
        $settings['billing_due_at'] = $validated['billing_due_at'] ?? null;
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

        return [
            'id' => $account->id,
            'name' => $account->brand_name ?: $account->name,
            'slug' => $account->slug,
            'domain' => $account->resolvedDomain(),
            'status' => $summary['status'],
            'due_at' => $summary['due_at'],
            'monthly_rent' => $settings['monthly_rent'] ?? null,
            'contract_reference' => $settings['contract_reference'] ?? null,
            'currency' => $account->default_currency,
            'is_active' => $account->is_active,
            'can_accept_leads' => $summary['can_accept_leads'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function billingForm(Account $account, AccountBillingService $billing): array
    {
        $settings = $account->settings ?? [];
        $summary = $billing->summary($account);

        return [
            'id' => $account->id,
            'name' => $account->brand_name ?: $account->name,
            'slug' => $account->slug,
            'domain' => $account->resolvedDomain(),
            'currency' => $account->default_currency,
            'status' => $summary['status'],
            'due_at' => $summary['due_at'],
            'locked_at' => $summary['locked_at'],
            'lock_reason' => $summary['lock_reason'],
            'monthly_rent' => $settings['monthly_rent'] ?? '',
            'contract_reference' => $settings['contract_reference'] ?? '',
            'billing_due_at' => $settings['billing_due_at'] ?? '',
            'billing_status' => $summary['status'],
            'billing_lock_reason' => $settings['billing_lock_reason'] ?? '',
            'billing_notes' => $settings['billing_notes'] ?? '',
            'billing_alert_emails' => $settings['billing_alert_emails'] ?? '',
            'can_accept_leads' => $summary['can_accept_leads'],
            'can_process_leads' => $summary['can_process_leads'],
        ];
    }
}
