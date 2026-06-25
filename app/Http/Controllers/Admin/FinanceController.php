<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Buyer;
use App\Models\BuyerTransaction;
use App\Models\Lead;
use App\Models\Supplier;
use App\Models\User;
use App\Enums\UserRole;
use App\Services\Billing\AccountBillingService;
use App\Support\Admin\ResolvesAdminAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class FinanceController extends Controller
{
    use ResolvesAdminAccount;

    public function index(Request $request): Response
    {
        $account = $this->resolveAdminAccount($request);
        $days = (int) $request->input('days', 30);
        $days = in_array($days, [7, 14, 30, 90], true) ? $days : 30;
        $since = today()->subDays($days);

        $buyerRows = Buyer::orderBy('name')
            ->get()
            ->map(function (Buyer $buyer) use ($since) {
                $sold = Lead::where('sold_to_buyer_id', $buyer->id)
                    ->where('status', 'sold')
                    ->whereDate('distributed_at', '>=', $since);

                return [
                    'id' => $buyer->id,
                    'name' => $buyer->name,
                    'reference' => $buyer->reference,
                    'status' => $buyer->status,
                    'credit_balance' => (float) $buyer->credit_balance,
                    'leads_sold' => (clone $sold)->count(),
                    'revenue' => (float) DB::table('lead_financials')
                        ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
                        ->where('leads.sold_to_buyer_id', $buyer->id)
                        ->whereDate('leads.distributed_at', '>=', $since)
                        ->sum('lead_financials.revenue'),
                    'portal_user' => User::where('buyer_id', $buyer->id)->where('role', UserRole::BuyerPortal)->first(['id', 'name', 'email', 'is_suspended']),
                ];
            });

        $supplierRows = Supplier::orderBy('name')
            ->get()
            ->map(function (Supplier $supplier) use ($since) {
                $leads = Lead::where('supplier_id', $supplier->id)
                    ->whereDate('received_at', '>=', $since);

                return [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                    'reference' => $supplier->reference,
                    'status' => $supplier->status,
                    'leads_submitted' => (clone $leads)->count(),
                    'leads_sold' => (clone $leads)->where('status', 'sold')->count(),
                    'payout' => (float) DB::table('lead_financials')
                        ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
                        ->where('leads.supplier_id', $supplier->id)
                        ->whereDate('leads.distributed_at', '>=', $since)
                        ->sum('lead_financials.payout'),
                    'portal_user' => User::where('supplier_id', $supplier->id)->where('role', UserRole::SupplierPortal)->first(['id', 'name', 'email', 'is_suspended']),
                ];
            });

        $summary = [
            'revenue' => (float) DB::table('lead_financials')
                ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
                ->where('leads.account_id', $account->id)
                ->whereDate('leads.distributed_at', '>=', $since)
                ->sum('lead_financials.revenue'),
            'payout' => (float) DB::table('lead_financials')
                ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
                ->where('leads.account_id', $account->id)
                ->whereDate('leads.distributed_at', '>=', $since)
                ->sum('lead_financials.payout'),
            'margin' => 0,
            'buyer_credit_total' => (float) Buyer::sum('credit_balance'),
            'transactions_period' => BuyerTransaction::whereDate('created_at', '>=', $since)->count(),
            'currency' => $account->default_currency ?? 'GBP',
        ];
        $summary['margin'] = $summary['revenue'] - $summary['payout'];

        return Inertia::render('Admin/Finance/Index', [
            'days' => $days,
            'summary' => $summary,
            'buyers' => $buyerRows,
            'suppliers' => $supplierRows,
            'accountBilling' => app(AccountBillingService::class)->summary($account),
        ]);
    }
}
