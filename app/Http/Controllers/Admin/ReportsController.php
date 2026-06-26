<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Services\Reports\ReportMetrics;
use App\Support\TenantFinancialSummary;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportsController extends Controller
{
    public function index(Request $request): Response
    {
        $metrics = ReportMetrics::fromRequest($request);
        $charts = $metrics->dailyCharts();
        $summary = $metrics->summary($charts);

        $account = $request->attributes->get('account') ?? $request->user()?->account;
        $accountId = $account?->id;

        $displayCurrency = $metrics->currency()
            ?? $account?->default_currency
            ?? 'GBP';

        $currenciesInUse = TenantFinancialSummary::currenciesInUse($accountId);
        $hasMultipleCurrencies = count($summary['revenue_by_currency'] ?? []) > 1 && ! $metrics->currency();

        $campaigns = Campaign::query()
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->orderBy('name')
            ->get(['id', 'name', 'reference', 'currency']);

        $pingTreeCampaigns = Campaign::with(['distributionConfigs' => fn ($q) => $q->where('is_active', true)])
            ->when($accountId, fn ($q) => $q->where('account_id', $accountId))
            ->whereHas('distributionConfigs', fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->get()
            ->map(function (Campaign $campaign) use ($accountId) {
                $activeConfig = $campaign->distributionConfigs
                    ->first(fn ($config) => str_contains(strtolower($config->name ?? ''), '10-tier'))
                    ?? $campaign->distributionConfigs->first();

                $tierQuery = Delivery::query()->where('campaign_id', $campaign->id)->whereNotNull('tier');
                if ($accountId) {
                    $tierQuery->whereHas('campaign', fn ($q) => $q->where('account_id', $accountId));
                }

                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'reference' => $campaign->reference,
                    'config_name' => $activeConfig?->name,
                    'tier_count' => (int) ($tierQuery->max('tier') ?? 0),
                ];
            })
            ->values()
            ->all();

        $selectedCampaign = $metrics->campaignId()
            ? $campaigns->firstWhere('id', $metrics->campaignId())
            : null;

        return Inertia::render('Admin/Reports/Index', [
            'days' => $metrics->days(),
            'month' => $metrics->month(),
            'periodLabel' => $metrics->periodLabel(),
            'currency' => $displayCurrency,
            'hasMultipleCurrencies' => $hasMultipleCurrencies,
            'currenciesInUse' => $currenciesInUse,
            'filters' => [
                'days' => $request->input('days', 28),
                'month' => $request->input('month'),
                'date_from' => $request->input('date_from'),
                'date_to' => $request->input('date_to'),
                'campaign_id' => $metrics->campaignId(),
                'currency' => $metrics->currency(),
            ],
            'filterOptions' => [
                'campaigns' => $campaigns->map(fn (Campaign $c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'reference' => $c->reference,
                    'currency' => $c->currency,
                ])->values()->all(),
                'currencies' => $currenciesInUse,
            ],
            'charts' => $charts,
            'byBuyer' => $metrics->byBuyer(),
            'bySupplier' => $metrics->bySupplier(),
            'byCampaign' => $metrics->byCampaign(),
            'bySid' => $metrics->bySid(),
            'deliveryPerformance' => $metrics->deliveryPerformance(),
            'tierSummary' => $metrics->tierSummary(),
            'distributionOutcome' => $metrics->distributionOutcome(),
            'leadStatusBreakdown' => $metrics->leadStatusBreakdown(),
            'pingTreeCampaigns' => $pingTreeCampaigns,
            'selectedCampaign' => $selectedCampaign ? [
                'id' => $selectedCampaign->id,
                'name' => $selectedCampaign->name,
                'reference' => $selectedCampaign->reference,
            ] : null,
            'summary' => $summary,
        ]);
    }
}
