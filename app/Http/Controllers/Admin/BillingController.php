<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Buyer;
use App\Models\BuyerTransaction;
use App\Services\Billing\AccountBillingService;
use App\Services\Billing\BuyerBillingService;
use App\Support\Admin\ResolvesAdminAccount;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    use ResolvesAdminAccount;

    public function index(Request $request): Response
    {
        $account = $this->resolveAdminAccount($request);
        $requirePrepay = $account->settings['require_buyer_prepay'] ?? false;

        $buyersQuery = Buyer::where('account_id', $account->id)->orderBy('name');

        $buyers = (clone $buyersQuery)
            ->paginate(25, ['*'], 'buyer_page')
            ->withQueryString()
            ->through(fn (Buyer $buyer) => [
                'id' => $buyer->id,
                'name' => $buyer->name,
                'reference' => $buyer->reference,
                'credit_balance' => $buyer->credit_balance,
                'status' => $buyer->status,
                'transaction_count' => $buyer->transactions()->count(),
            ]);

        $summary = [
            'total_credit' => (float) (clone $buyersQuery)->sum('credit_balance'),
            'buyer_count' => (clone $buyersQuery)->count(),
            'transactions_today' => BuyerTransaction::query()
                ->whereHas('buyer', fn ($q) => $q->where('account_id', $account->id))
                ->whereDate('created_at', today())
                ->count(),
            'require_prepay' => $requirePrepay,
            'currency' => $account->default_currency ?? 'GBP',
        ];

        $recentTransactions = BuyerTransaction::query()
            ->whereHas('buyer', fn ($q) => $q->where('account_id', $account->id))
            ->with('buyer:id,name,account_id')
            ->orderByDesc('created_at')
            ->paginate(25, ['*'], 'txn_page')
            ->withQueryString();

        return Inertia::render('Admin/Billing/Index', [
            'buyers' => $buyers,
            'summary' => $summary,
            'recentTransactions' => $recentTransactions,
            'accountBilling' => app(AccountBillingService::class)->summary($account),
        ]);
    }

    public function show(Request $request, Buyer $buyer): Response
    {
        $this->resolveAdminAccountForTenant($request, $buyer->account_id);
        $currency = $buyer->resolvedCurrency();

        return Inertia::render('Admin/Billing/Show', [
            'buyer' => [
                ...$buyer->only(['id', 'name', 'reference', 'credit_balance', 'status']),
                'currency' => $buyer->resolvedCurrency(),
                'low_credit_alert' => app(\App\Services\Billing\BuyerCreditAlertService::class)->thresholdFor($buyer),
                'is_low_credit' => app(\App\Services\Billing\BuyerCreditAlertService::class)->isBelowThreshold($buyer),
            ],
            'transactions' => $buyer->transactions()->orderByDesc('created_at')->paginate(25),
            'currency' => $buyer->resolvedCurrency(),
            'ledgerTypes' => [
                ['value' => 'credit', 'label' => 'Credit (top-up)'],
                ['value' => 'goodwill', 'label' => 'Goodwill credit'],
                ['value' => 'correction', 'label' => 'Balance correction'],
                ['value' => 'refund', 'label' => 'Refund'],
                ['value' => 'manual_debit', 'label' => 'Manual debit'],
                ['value' => 'chargeback', 'label' => 'Chargeback'],
                ['value' => 'adjustment', 'label' => 'General adjustment'],
            ],
        ]);
    }

    public function topUp(Request $request, Buyer $buyer): RedirectResponse
    {
        $this->resolveAdminAccountForTenant($request, $buyer->account_id);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:999999',
            'description' => 'nullable|string|max:255',
            'type' => 'nullable|in:credit,goodwill,correction,refund,manual_debit,chargeback,adjustment',
            'bypass_account_lock' => 'boolean',
            'allow_negative' => 'boolean',
            'suppress_alerts' => 'boolean',
            'skip_ledger' => 'boolean',
        ]);

        $type = $validated['type'] ?? 'credit';

        try {
            app(BuyerBillingService::class)->adjust(
                $buyer,
                (float) $validated['amount'],
                $type,
                $validated['description'] ?? ucfirst(str_replace('_', ' ', $type)),
                [
                    'bypass_account_lock' => $request->boolean('bypass_account_lock'),
                    'allow_negative' => $request->boolean('allow_negative'),
                    'suppress_alerts' => $request->boolean('suppress_alerts'),
                    'skip_ledger' => $request->boolean('skip_ledger'),
                    'performed_by' => $request->user()?->id,
                ]
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['amount' => $e->getMessage()]);
        }

        return back()->with('success', 'Ledger entry recorded.');
    }

    public function export(Request $request, Buyer $buyer)
    {
        $this->resolveAdminAccountForTenant($request, $buyer->account_id);

        $currency = $buyer->resolvedCurrency();

        $transactions = $buyer->transactions()->orderByDesc('created_at')->limit(5000)->get();

        $csv = "date,type,amount,balance_after,description,currency\n";

        foreach ($transactions as $txn) {
            $csv .= CsvExport::escapeRow([
                $txn->created_at,
                $txn->type,
                $txn->amount,
                $txn->balance_after,
                $txn->description,
                $currency,
            ])."\n";
        }

        return CsvExport::download($csv, 'billing-'.$buyer->reference.'-'.now()->format('Y-m-d-His').'.csv');
    }

    public function exportAll(Request $request)
    {
        $account = $this->resolveAdminAccount($request);

        $transactions = BuyerTransaction::query()
            ->with('buyer:id,name,reference,currency,account_id')
            ->whereHas('buyer', fn ($q) => $q->where('account_id', $account->id))
            ->orderByDesc('created_at')
            ->limit(5000)
            ->get();

        $csv = "date,buyer,buyer_reference,type,amount,balance_after,description,currency\n";

        foreach ($transactions as $txn) {
            $currency = $txn->buyer?->resolvedCurrency() ?? $account->default_currency ?? 'GBP';

            $csv .= CsvExport::escapeRow([
                $txn->created_at,
                $txn->buyer?->name ?? '',
                $txn->buyer?->reference ?? '',
                $txn->type,
                $txn->amount,
                $txn->balance_after,
                $txn->description,
                $currency,
            ])."\n";
        }

        return CsvExport::download($csv, 'billing-ledger-'.now()->format('Y-m-d-His').'.csv');
    }

    public function unlockAccount(Request $request): RedirectResponse
    {
        $account = $this->resolveAdminAccount($request);
        app(AccountBillingService::class)->unlock($account);

        return redirect()->route('dashboard')->with('success', 'Account billing unlocked.');
    }
}
