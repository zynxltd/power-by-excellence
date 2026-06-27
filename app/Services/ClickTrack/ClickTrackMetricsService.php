<?php

namespace App\Services\ClickTrack;

use App\Models\TrackingClick;
use App\Models\TrackingConversion;
use App\Models\TrackingImpression;
use App\Models\TrackingLink;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ClickTrackMetricsService
{
    public static function fromRequest(Request $request): self
    {
        $account = $request->attributes->get('account') ?? $request->user()?->resolveAccount();
        $since = $request->filled('date_from')
            ? Carbon::parse($request->date_from)->startOfDay()
            : now()->subDays((int) $request->input('days', 7))->startOfDay();
        $until = $request->filled('date_to')
            ? Carbon::parse($request->date_to)->endOfDay()
            : now()->endOfDay();

        return new self(
            accountId: $account?->id,
            since: $since,
            until: $until,
            campaignId: $request->integer('campaign_id') ?: null,
            supplierId: $request->integer('supplier_id') ?: null,
            groupBy: $request->input('group_by', 'offer'),
        );
    }

    public function __construct(
        protected ?int $accountId = null,
        protected ?Carbon $since = null,
        protected ?Carbon $until = null,
        protected ?int $campaignId = null,
        protected ?int $supplierId = null,
        protected string $groupBy = 'offer',
    ) {
        $this->since ??= now()->subDays(7)->startOfDay();
        $this->until ??= now()->endOfDay();
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboardSummary(): array
    {
        $clicksQuery = $this->baseClicksQuery();
        $conversionsQuery = $this->baseConversionsQuery();
        $impressions = $this->baseImpressionsQuery()->count();

        $clicks = (clone $clicksQuery)->count();
        $uniqueClicks = (clone $clicksQuery)->where('is_unique', true)->count();
        $conversions = (clone $conversionsQuery)->count();
        $approved = (clone $conversionsQuery)->where('status', TrackingConversion::STATUS_APPROVED)->count();
        $pending = (clone $conversionsQuery)->where('status', TrackingConversion::STATUS_PENDING)->count();
        $rejected = (clone $conversionsQuery)->where('status', TrackingConversion::STATUS_REJECTED)->count();

        $financials = (clone $conversionsQuery)
            ->where('status', TrackingConversion::STATUS_APPROVED)
            ->selectRaw('COALESCE(SUM(revenue), 0) as revenue, COALESCE(SUM(payout), 0) as payout')
            ->first();

        return [
            'impressions' => $impressions,
            'clicks' => $clicks,
            'unique_clicks' => $uniqueClicks,
            'conversions' => $conversions,
            'approved' => $approved,
            'pending' => $pending,
            'rejected' => $rejected,
            'revenue' => (float) ($financials->revenue ?? 0),
            'payout' => (float) ($financials->payout ?? 0),
            'margin' => (float) ($financials->revenue ?? 0) - (float) ($financials->payout ?? 0),
            'ctr' => $impressions > 0 ? round(($clicks / $impressions) * 100, 2) : null,
            'cr' => $clicks > 0 ? round(($approved / $clicks) * 100, 2) : null,
            'today' => [
                'clicks' => $this->countClicksSince(now()->startOfDay()),
                'conversions' => $this->countConversionsSince(now()->startOfDay()),
                'revenue' => $this->sumRevenueSince(now()->startOfDay()),
            ],
            'pending_actions' => [
                'conversions' => TrackingConversion::query()
                    ->when($this->accountId, fn ($q) => $q->where('account_id', $this->accountId))
                    ->where('status', TrackingConversion::STATUS_PENDING)
                    ->count(),
                'links' => TrackingLink::query()
                    ->when($this->accountId, fn ($q) => $q->where('account_id', $this->accountId))
                    ->where('status', 'paused')
                    ->count(),
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function performanceRows(): array
    {
        $clicks = $this->baseClicksQuery()
            ->with(['trackingLink:id,name', 'supplier:id,name'])
            ->get();

        $approvedByClick = TrackingConversion::query()
            ->when($this->accountId, fn ($q) => $q->where('account_id', $this->accountId))
            ->where('status', TrackingConversion::STATUS_APPROVED)
            ->whereBetween('created_at', [$this->since, $this->until])
            ->whereNotNull('tracking_click_id')
            ->get()
            ->groupBy('tracking_click_id');

        $groups = [];

        foreach ($clicks as $click) {
            $key = match ($this->groupBy) {
                'affiliate' => 'affiliate:'.($click->supplier_id ?? '0'),
                'date' => 'date:'.$click->clicked_at->toDateString(),
                default => 'offer:'.$click->tracking_link_id,
            };

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'label' => match ($this->groupBy) {
                        'affiliate' => $click->supplier?->name ?? 'Direct',
                        'date' => $click->clicked_at->toDateString(),
                        default => $click->trackingLink?->name ?? 'Offer #'.$click->tracking_link_id,
                    },
                    'clicks' => 0,
                    'unique_clicks' => 0,
                    'conversions' => 0,
                    'revenue' => 0.0,
                    'payout' => 0.0,
                ];
            }

            $groups[$key]['clicks']++;
            if ($click->is_unique) {
                $groups[$key]['unique_clicks']++;
            }

            foreach ($approvedByClick->get($click->id, collect()) as $conversion) {
                $groups[$key]['conversions']++;
                $groups[$key]['revenue'] += (float) $conversion->revenue;
                $groups[$key]['payout'] += (float) $conversion->payout;
            }
        }

        return collect($groups)
            ->map(function (array $row) {
                $clicks = (int) $row['clicks'];
                $conversions = (int) $row['conversions'];

                return [
                    ...$row,
                    'cr' => $clicks > 0 ? round(($conversions / $clicks) * 100, 2) : 0,
                ];
            })
            ->sortByDesc('clicks')
            ->values()
            ->take(100)
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function conversionStatusByDate(): array
    {
        return TrackingConversion::query()
            ->when($this->accountId, fn ($q) => $q->where('account_id', $this->accountId))
            ->when($this->campaignId, fn ($q) => $q->where('campaign_id', $this->campaignId))
            ->when($this->supplierId, fn ($q) => $q->where('supplier_id', $this->supplierId))
            ->whereBetween('created_at', [$this->since, $this->until])
            ->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as gross,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as approved,
                SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as rejected,
                COALESCE(SUM(CASE WHEN status = ? THEN payout ELSE 0 END), 0) as payout,
                COALESCE(SUM(CASE WHEN status = ? THEN revenue ELSE 0 END), 0) as revenue
            ', [
                TrackingConversion::STATUS_PENDING,
                TrackingConversion::STATUS_APPROVED,
                TrackingConversion::STATUS_REJECTED,
                TrackingConversion::STATUS_APPROVED,
                TrackingConversion::STATUS_APPROVED,
            ])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'gross' => (int) $row->gross,
                'pending' => (int) $row->pending,
                'approved' => (int) $row->approved,
                'rejected' => (int) $row->rejected,
                'approved_pct' => $row->gross > 0 ? round(($row->approved / $row->gross) * 100, 1) : 0,
                'rejected_pct' => $row->gross > 0 ? round(($row->rejected / $row->gross) * 100, 1) : 0,
                'payout' => (float) $row->payout,
                'revenue' => (float) $row->revenue,
            ])
            ->all();
    }

    protected function baseClicksQuery()
    {
        return TrackingClick::query()
            ->when($this->accountId, fn ($q) => $q->where('tracking_clicks.account_id', $this->accountId))
            ->when($this->campaignId, fn ($q) => $q->where('tracking_clicks.campaign_id', $this->campaignId))
            ->when($this->supplierId, fn ($q) => $q->where('tracking_clicks.supplier_id', $this->supplierId))
            ->whereBetween('tracking_clicks.clicked_at', [$this->since, $this->until]);
    }

    protected function baseConversionsQuery()
    {
        return TrackingConversion::query()
            ->when($this->accountId, fn ($q) => $q->where('account_id', $this->accountId))
            ->when($this->campaignId, fn ($q) => $q->where('campaign_id', $this->campaignId))
            ->when($this->supplierId, fn ($q) => $q->where('supplier_id', $this->supplierId))
            ->whereBetween('created_at', [$this->since, $this->until]);
    }

    protected function baseImpressionsQuery()
    {
        return TrackingImpression::query()
            ->when($this->accountId, fn ($q) => $q->where('account_id', $this->accountId))
            ->whereBetween('impressed_at', [$this->since, $this->until]);
    }

    protected function countClicksSince(Carbon $since): int
    {
        return TrackingClick::query()
            ->when($this->accountId, fn ($q) => $q->where('account_id', $this->accountId))
            ->where('clicked_at', '>=', $since)
            ->count();
    }

    protected function countConversionsSince(Carbon $since): int
    {
        return TrackingConversion::query()
            ->when($this->accountId, fn ($q) => $q->where('account_id', $this->accountId))
            ->where('created_at', '>=', $since)
            ->count();
    }

    protected function sumRevenueSince(Carbon $since): float
    {
        return (float) TrackingConversion::query()
            ->when($this->accountId, fn ($q) => $q->where('account_id', $this->accountId))
            ->where('status', TrackingConversion::STATUS_APPROVED)
            ->where('created_at', '>=', $since)
            ->sum('revenue');
    }
}
