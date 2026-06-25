<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Services\Reports\ReportMetrics;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function index(Request $request): Response
    {
        $metrics = ReportMetrics::fromRequest($request);
        $charts = $metrics->dailyCharts();

        $account = $request->attributes->get('account') ?? $request->user()?->account;
        $currency = $account?->default_currency ?? 'GBP';

        $distributionOutcome = $metrics->distributionOutcome();

        $pingTreeCampaign = Campaign::with('distributionConfigs')
            ->whereHas('distributionConfigs', fn ($q) => $q->where('is_active', true))
            ->orderByDesc('updated_at')
            ->first();

        $activeConfig = $pingTreeCampaign?->distributionConfigs
            ->first(fn ($config) => str_contains(strtolower($config->name ?? ''), '10-tier'))
            ?? $pingTreeCampaign?->distributionConfigs->first();

        return Inertia::render('Admin/Reports/Index', [
            'days' => $metrics->days(),
            'month' => $metrics->month(),
            'currency' => $currency,
            'charts' => $charts,
            'byBuyer' => $metrics->byBuyer(),
            'bySupplier' => $metrics->bySupplier(),
            'byCampaign' => $metrics->byCampaign(),
            'bySid' => $metrics->bySid(),
            'deliveryPerformance' => $metrics->deliveryPerformance(),
            'tierSummary' => $metrics->tierSummary(),
            'distributionOutcome' => $distributionOutcome,
            'leadStatusBreakdown' => $metrics->leadStatusBreakdown(),
            'pingTree' => [
                'campaign_name' => $pingTreeCampaign?->name,
                'campaign_id' => $pingTreeCampaign?->id,
                'config_name' => $activeConfig?->name,
                'tier_count' => Delivery::forTenant()->where('campaign_id', $pingTreeCampaign?->id)->whereNotNull('tier')->max('tier') ?? 0,
            ],
            'summary' => $metrics->summary($charts),
        ]);
    }
}
