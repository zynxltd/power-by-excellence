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

        $dateFrom = $request->string('from_date')->toString();
        $dateTo = $request->string('to_date')->toString();

        if ($dateFrom && $dateTo
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $since = \Carbon\Carbon::createFromFormat('Y-m-d', $dateFrom)->startOfDay();
            $until = \Carbon\Carbon::createFromFormat('Y-m-d', $dateTo)->endOfDay();
            $days = min($since->diffInDays($until) + 1, 366);
        } else {
            $days = (int) $request->input('days', 30);
            $days = in_array($days, [7, 14, 28, 30, 60, 90], true) ? $days : 30;
            $since = today()->subDays($days - 1)->startOfDay();
            $until = now()->endOfDay();
        }

        $buyerRows = Buyer::orderBy('name')
            ->get()
            ->map(function (Buyer $buyer) use ($since, $until) {
                $sold = Lead::where('sold_to_buyer_id', $buyer->id)
                    ->where('status', 'sold')
                    ->whereDate('distributed_at', '>=', $since)
                    ->whereDate('distributed_at', '<=', $until);

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
                        ->whereDate('leads.distributed_at', '<=', $until)
                        ->sum('lead_financials.revenue'),
                    'portal_user' => User::where('buyer_id', $buyer->id)->where('role', UserRole::BuyerPortal)->first(['id', 'name', 'email', 'is_suspended']),
                ];
            });

        $supplierRows = Supplier::orderBy('name')
            ->get()
            ->map(function (Supplier $supplier) use ($since, $until) {
                $leads = Lead::where('supplier_id', $supplier->id)
                    ->whereDate('received_at', '>=', $since)
                    ->whereDate('received_at', '<=', $until);

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
                        ->whereDate('leads.distributed_at', '<=', $until)
                        ->sum('lead_financials.payout'),
                    'portal_user' => User::where('supplier_id', $supplier->id)->where('role', UserRole::SupplierPortal)->first(['id', 'name', 'email', 'is_suspended']),
                ];
            });

        $summary = [
            'revenue' => (float) DB::table('lead_financials')
                ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
                ->where('leads.account_id', $account->id)
                ->whereDate('leads.distributed_at', '>=', $since)
                ->whereDate('leads.distributed_at', '<=', $until)
                ->sum('lead_financials.revenue'),
            'payout' => (float) DB::table('lead_financials')
                ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
                ->where('leads.account_id', $account->id)
                ->whereDate('leads.distributed_at', '>=', $since)
                ->whereDate('leads.distributed_at', '<=', $until)
                ->sum('lead_financials.payout'),
            'margin' => 0,
            'buyer_credit_total' => (float) Buyer::sum('credit_balance'),
            'transactions_period' => BuyerTransaction::whereBetween('created_at', [$since, $until])->count(),
            'currency' => $account->default_currency ?? 'GBP',
        ];
        $summary['margin'] = $summary['revenue'] - $summary['payout'];

        return Inertia::render('Admin/Finance/Index', [
            'days' => $days,
            'filters' => [
                'days' => $request->input('days', 30),
                'from_date' => $dateFrom ?: null,
                'to_date' => $dateTo ?: null,
            ],
            'summary' => $summary,
            'buyers' => $buyerRows,
            'suppliers' => $supplierRows,
            'accountBilling' => app(AccountBillingService::class)->summary($account),
        ]);
    }
}
