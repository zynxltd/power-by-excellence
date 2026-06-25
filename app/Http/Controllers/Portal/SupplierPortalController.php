<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SupplierPortalController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $supplier = $request->user()->supplier;

        $stats = [
            'leads_today' => Lead::where('supplier_id', $supplier->id)->whereDate('received_at', today())->count(),
            'sold_today' => Lead::where('supplier_id', $supplier->id)->whereDate('distributed_at', today())->where('status', 'sold')->count(),
            'revenue_today' => DB::table('lead_financials')
                ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
                ->where('leads.supplier_id', $supplier->id)
                ->whereDate('leads.distributed_at', today())
                ->sum('lead_financials.payout'),
        ];

        $sources = $supplier->sources()->withCount([
            'subSuppliers',
        ])->get();

        return Inertia::render('Portal/Supplier/Dashboard', [
            'supplier' => $supplier,
            'stats' => $stats,
            'sources' => $sources,
            'charts' => $this->supplierCharts($supplier->id),
            'currency' => $request->user()->account?->default_currency ?? 'GBP',
        ]);
    }

    protected function supplierCharts(int $supplierId): array
    {
        $labels = [];
        $leads = [];
        $sold = [];
        $payout = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = today()->subDays($i);
            $labels[] = $date->format('D');
            $leads[] = Lead::where('supplier_id', $supplierId)->whereDate('received_at', $date)->count();
            $sold[] = Lead::where('supplier_id', $supplierId)->whereDate('distributed_at', $date)->where('status', 'sold')->count();
            $payout[] = (float) DB::table('lead_financials')
                ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
                ->where('leads.supplier_id', $supplierId)
                ->whereDate('leads.distributed_at', $date)
                ->sum('lead_financials.payout');
        }

        return compact('labels', 'leads', 'sold', 'payout');
    }

    public function leads(Request $request): Response
    {
        $supplier = $request->user()->supplier;
        abort_unless($supplier, 403, 'Supplier account not linked to this user.');

        $query = Lead::where('supplier_id', $supplier->id)
            ->with(['campaign', 'financials']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->integer('campaign_id'));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('uuid', 'like', "%{$search}%")
                    ->orWhere('field_data->email', 'like', "%{$search}%");
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('received_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('received_at', '<=', $request->to_date);
        }

        $leads = $query->orderByDesc('received_at')->paginate(25)->withQueryString();

        $campaigns = Lead::where('supplier_id', $supplier->id)
            ->join('campaigns', 'campaigns.id', '=', 'leads.campaign_id')
            ->distinct()
            ->orderBy('campaigns.name')
            ->get(['campaigns.id', 'campaigns.name', 'campaigns.reference']);

        return Inertia::render('Portal/Supplier/Leads', [
            'leads' => $leads,
            'filters' => $request->only(['status', 'campaign_id', 'search', 'from_date', 'to_date']),
            'campaigns' => $campaigns,
            'statuses' => ['pending', 'processing', 'sold', 'unsold', 'rejected', 'quarantined', 'duplicate'],
        ]);
    }

    public function downloadLeads(Request $request)
    {
        $supplier = $request->user()->supplier;
        abort_unless($supplier, 403);

        $query = Lead::where('supplier_id', $supplier->id);

        if ($request->filled('from_date')) {
            $query->whereDate('received_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('received_at', '<=', $request->to_date);
        }

        $leads = $query->orderByDesc('received_at')->limit(5000)->get();

        $csv = "uuid,campaign,status,firstname,lastname,email,phone,payout,received_at,distributed_at\n";
        foreach ($leads as $lead) {
            $csv .= implode(',', [
                $lead->uuid,
                $lead->campaign?->reference ?? '',
                $lead->status->value,
                $lead->getField('firstname'),
                $lead->getField('lastname'),
                $lead->getField('email'),
                $lead->getField('phone1'),
                $lead->financials?->payout ?? 0,
                $lead->received_at,
                $lead->distributed_at,
            ])."\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="supplier-leads.csv"',
        ]);
    }

    public function billing(Request $request): Response
    {
        $supplier = $request->user()->supplier;
        $account = $request->user()->account ?? $supplier->account;

        $totalPayout = (float) DB::table('lead_financials')
            ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
            ->where('leads.supplier_id', $supplier->id)
            ->sum('lead_financials.payout');

        $payoutThisMonth = (float) DB::table('lead_financials')
            ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
            ->where('leads.supplier_id', $supplier->id)
            ->whereMonth('leads.distributed_at', now()->month)
            ->whereYear('leads.distributed_at', now()->year)
            ->sum('lead_financials.payout');

        $recentPayouts = Lead::where('supplier_id', $supplier->id)
            ->where('status', 'sold')
            ->with('financials')
            ->orderByDesc('distributed_at')
            ->limit(25)
            ->get()
            ->map(fn (Lead $lead) => [
                'uuid' => $lead->uuid,
                'payout' => $lead->financials?->payout ?? 0,
                'distributed_at' => $lead->distributed_at?->toDateTimeString(),
            ]);

        return Inertia::render('Portal/Supplier/Billing', [
            'supplier' => $supplier,
            'currency' => $account?->default_currency ?? 'GBP',
            'summary' => [
                'total_payout' => $totalPayout,
                'payout_this_month' => $payoutThisMonth,
                'sold_count' => Lead::where('supplier_id', $supplier->id)->where('status', 'sold')->count(),
            ],
            'recentPayouts' => $recentPayouts,
        ]);
    }
}
