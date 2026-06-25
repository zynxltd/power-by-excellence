<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryLog;
use App\Models\Lead;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OperationsController extends Controller
{
    public function index(Request $request): Response
    {
        $stats = [
            'leads_today' => Lead::whereDate('received_at', today())->count(),
            'sold_today' => Lead::where('status', 'sold')->whereDate('distributed_at', today())->count(),
            'unsold_today' => Lead::where('status', 'unsold')->whereDate('received_at', today())->count(),
            'pending' => Lead::whereIn('status', ['pending', 'processing'])->count(),
            'quarantined' => Lead::where('status', 'quarantined')->count(),
            'rejected_today' => Lead::where('status', 'rejected')->whereDate('received_at', today())->count(),
            'ping_posts_today' => DeliveryLog::query()->forCurrentAccount()->whereDate('created_at', today())
                ->whereNotNull('ping_request')
                ->count(),
            'failed_today' => DeliveryLog::query()->forCurrentAccount()->whereDate('created_at', today())
                ->whereIn('status', ['failed', 'skipped'])
                ->count(),
            'revenue_today' => (float) \Illuminate\Support\Facades\DB::table('lead_financials')
                ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
                ->whereDate('leads.distributed_at', today())
                ->sum('lead_financials.revenue'),
        ];

        $topCampaigns = Lead::query()
            ->join('campaigns', 'campaigns.id', '=', 'leads.campaign_id')
            ->whereDate('leads.received_at', today())
            ->groupBy('campaigns.id', 'campaigns.name')
            ->selectRaw("campaigns.id as id, campaigns.name as name, count(*) as leads, sum(case when leads.status = 'sold' then 1 else 0 end) as sold")
            ->orderByDesc('leads')
            ->limit(5)
            ->get();

        $queueBreakdown = Lead::query()
            ->selectRaw('status, count(*) as count')
            ->whereIn('status', ['pending', 'processing', 'quarantined', 'accepted'])
            ->groupBy('status')
            ->pluck('count', 'status');

        $hourlyLeads = [];
        for ($h = 23; $h >= 0; $h--) {
            $hour = now()->subHours($h);
            $hourlyLeads[] = [
                'label' => $hour->format('H:00'),
                'count' => Lead::whereBetween('received_at', [$hour->copy()->startOfHour(), $hour->copy()->endOfHour()])->count(),
            ];
        }

        $recentLeads = Lead::with(['campaign', 'soldToBuyer', 'supplier'])
            ->orderByDesc('received_at')
            ->paginate(20, ['*'], 'lead_page')
            ->withQueryString()
            ->through(fn (Lead $lead) => [
                'id' => $lead->id,
                'uuid' => $lead->uuid,
                'status' => $lead->status->value ?? $lead->status,
                'campaign' => $lead->campaign?->name,
                'campaign_id' => $lead->campaign_id,
                'buyer' => $lead->soldToBuyer?->name,
                'supplier' => $lead->supplier?->name,
                'received_at' => $lead->received_at?->toDateTimeString(),
            ]);

        $deliveryPreview = DeliveryLog::query()
            ->forCurrentAccount()
            ->with(['lead', 'delivery', 'buyer'])
            ->orderByDesc('created_at')
            ->paginate(15, ['*'], 'delivery_page')
            ->withQueryString()
            ->through(fn (DeliveryLog $log) => [
                'id' => $log->id,
                'status' => $log->status,
                'delivery' => $log->delivery?->name,
                'delivery_id' => $log->delivery_id,
                'buyer' => $log->buyer?->name,
                'tier' => $log->delivery?->tier,
                'lead_id' => $log->lead_id,
                'lead_uuid' => $log->lead?->uuid,
                'method' => $log->ping_request ? 'ping-post' : 'direct',
                'revenue' => $log->revenue,
                'duration_ms' => $log->duration_ms,
                'created_at' => $log->created_at?->toDateTimeString(),
            ]);

        return Inertia::render('Admin/Operations/Index', [
            'stats' => $stats,
            'queueBreakdown' => $queueBreakdown,
            'hourlyLeads' => $hourlyLeads,
            'topCampaigns' => $topCampaigns,
            'recentLeads' => $recentLeads,
            'deliveryPreview' => $deliveryPreview,
        ]);
    }
}
