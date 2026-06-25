<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function index(Request $request): Response
    {
        $month = $request->string('month')->toString();
        $days = (int) $request->input('days', 28);
        $days = in_array($days, [7, 14, 28, 30], true) ? $days : 28;

        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $start = \Carbon\Carbon::createFromFormat('Y-m', $month)->startOfMonth();
            $since = $start->copy();
            $days = $start->daysInMonth;
        } else {
            $since = today()->subDays($days);
            $month = null;
            $start = null;
        }

        $account = $request->attributes->get('account') ?? $request->user()?->account;
        $currency = $account?->default_currency ?? 'GBP';
        $accountId = $account?->id;

        $deliveryLogs = DB::table('delivery_logs')
            ->join('leads', 'leads.id', '=', 'delivery_logs.lead_id')
            ->when($accountId, fn ($q) => $q->where('leads.account_id', $accountId));

        $labels = [];
        $leads = [];
        $sold = [];
        $rejected = [];
        $revenue = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = $start
                ? $start->copy()->addDays($days - 1 - $i)
                : today()->subDays($i);
            $labels[] = $date->format('D j M');
            $leads[] = Lead::whereDate('received_at', $date)->count();
            $sold[] = Lead::whereDate('distributed_at', $date)->where('status', 'sold')->count();
            $rejected[] = Lead::whereDate('received_at', $date)->where('status', 'rejected')->count();
            $revenue[] = (float) DB::table('lead_financials')
                ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
                ->whereDate('leads.distributed_at', $date)
                ->sum('lead_financials.revenue');
        }

        $byBuyer = DB::table('leads')
            ->join('buyers', 'buyers.id', '=', 'leads.sold_to_buyer_id')
            ->join('lead_financials', 'lead_financials.lead_id', '=', 'leads.id')
            ->where('leads.status', 'sold')
            ->whereDate('leads.distributed_at', '>=', $since)
            ->groupBy('buyers.id', 'buyers.name')
            ->selectRaw('buyers.id as buyer_id, buyers.name as name, count(*) as leads, sum(lead_financials.revenue) as revenue')
            ->orderByDesc('revenue')
            ->paginate(10, ['*'], 'buyer_page')
            ->withQueryString();

        $bySupplier = DB::table('leads')
            ->join('suppliers', 'suppliers.id', '=', 'leads.supplier_id')
            ->join('lead_financials', 'lead_financials.lead_id', '=', 'leads.id')
            ->where('leads.status', 'sold')
            ->whereDate('leads.distributed_at', '>=', $since)
            ->groupBy('suppliers.id', 'suppliers.name')
            ->selectRaw('suppliers.id as supplier_id, suppliers.name as name, count(*) as leads, sum(lead_financials.payout) as payout')
            ->orderByDesc('payout')
            ->paginate(10, ['*'], 'supplier_page')
            ->withQueryString();

        $deliveryPerformance = (clone $deliveryLogs)
            ->join('deliveries', 'deliveries.id', '=', 'delivery_logs.delivery_id')
            ->join('buyers', 'buyers.id', '=', 'deliveries.buyer_id')
            ->whereDate('delivery_logs.created_at', '>=', $since)
            ->groupBy('deliveries.id', 'deliveries.name', 'deliveries.method', 'deliveries.tier', 'deliveries.campaign_id', 'buyers.name')
            ->selectRaw("deliveries.id as delivery_id, deliveries.campaign_id as campaign_id, deliveries.name as name, deliveries.method as method, deliveries.tier as tier, buyers.name as buyer_name, count(*) as attempts, sum(case when delivery_logs.status = 'success' then 1 else 0 end) as successes, sum(case when delivery_logs.status = 'outbid' then 1 else 0 end) as outbid, sum(case when delivery_logs.status in ('failed','skipped') then 1 else 0 end) as rejections, sum(delivery_logs.revenue) as revenue")
            ->orderBy('deliveries.tier')
            ->orderByDesc('attempts')
            ->paginate(15, ['*'], 'delivery_page')
            ->withQueryString();

        $tierSummary = (clone $deliveryLogs)
            ->join('deliveries', 'deliveries.id', '=', 'delivery_logs.delivery_id')
            ->whereDate('delivery_logs.created_at', '>=', $since)
            ->whereNotNull('deliveries.tier')
            ->groupBy('deliveries.tier')
            ->selectRaw("deliveries.tier as tier, count(*) as attempts, sum(case when delivery_logs.status = 'success' then 1 else 0 end) as wins, sum(case when delivery_logs.status = 'outbid' then 1 else 0 end) as outbid, sum(case when delivery_logs.status in ('failed','skipped') then 1 else 0 end) as rejections, sum(delivery_logs.revenue) as revenue")
            ->orderBy('deliveries.tier')
            ->paginate(10, ['*'], 'tier_page')
            ->withQueryString();

        $distributionOutcome = (clone $deliveryLogs)
            ->whereDate('delivery_logs.created_at', '>=', $since)
            ->selectRaw("delivery_logs.status, count(*) as total")
            ->groupBy('delivery_logs.status')
            ->pluck('total', 'status');

        $pingTreeCampaign = Campaign::with('distributionConfigs')
            ->whereHas('distributionConfigs', fn ($q) => $q->where('is_active', true))
            ->orderByDesc('updated_at')
            ->first();

        return Inertia::render('Admin/Reports/Index', [
            'days' => $days,
            'month' => $month,
            'currency' => $currency,
            'charts' => compact('labels', 'leads', 'sold', 'rejected', 'revenue'),
            'byBuyer' => $byBuyer,
            'bySupplier' => $bySupplier,
            'deliveryPerformance' => $deliveryPerformance,
            'tierSummary' => $tierSummary,
            'distributionOutcome' => $distributionOutcome,
            'pingTree' => [
                'campaign_name' => $pingTreeCampaign?->name,
                'campaign_id' => $pingTreeCampaign?->id,
                'config_name' => $pingTreeCampaign?->distributionConfigs->firstWhere('name', 'like', '%10-Tier%')?->name
                    ?? $pingTreeCampaign?->distributionConfigs->first()?->name,
                'tier_count' => Delivery::forTenant()->where('campaign_id', $pingTreeCampaign?->id)->whereNotNull('tier')->max('tier') ?? 0,
            ],
            'summary' => [
                'leads_period' => array_sum($leads),
                'sold_period' => array_sum($sold),
                'unsold_period' => Lead::where('status', 'unsold')->whereDate('received_at', '>=', $since)->count(),
                'rejected_period' => array_sum($rejected),
                'revenue_period' => array_sum($revenue),
                'payout_period' => (float) DB::table('lead_financials')
                    ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
                    ->whereDate('leads.distributed_at', '>=', $since)
                    ->sum('lead_financials.payout'),
                'conversion' => array_sum($leads) > 0 ? round((array_sum($sold) / array_sum($leads)) * 100, 1) : 0,
                'outbid_total' => (int) ($distributionOutcome['outbid'] ?? 0),
                'ping_rejections' => (int) (($distributionOutcome['failed'] ?? 0) + ($distributionOutcome['skipped'] ?? 0)),
                'margin_period' => array_sum($revenue) - (float) DB::table('lead_financials')
                    ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
                    ->when($accountId, fn ($q) => $q->where('leads.account_id', $accountId))
                    ->whereDate('leads.distributed_at', '>=', $since)
                    ->sum('lead_financials.payout'),
            ],
        ]);
    }
}
