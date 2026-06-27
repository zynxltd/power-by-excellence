<?php

namespace App\Services\Buyers;

use App\Models\Buyer;
use App\Models\BuyerFeedback;
use App\Models\Lead;
use App\Models\LeadReturn;
use App\Services\Billing\BuyerCreditAlertService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BuyerPortalService
{
    public function __construct(
        protected BuyerCreditAlertService $creditAlerts,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function dashboardStats(Buyer $buyer): array
    {
        $buyerId = $buyer->id;
        $soldQuery = Lead::query()->where('sold_to_buyer_id', $buyerId);

        $convertedCount = BuyerFeedback::query()
            ->where('buyer_id', $buyerId)
            ->where('converted', true)
            ->count();

        $totalLeads = (clone $soldQuery)->count();
        $spendToday = $this->sumRevenue($buyerId, today(), today());
        $spend7d = $this->sumRevenue($buyerId, today()->subDays(6), today());
        $spend30d = $this->sumRevenue($buyerId, today()->subDays(29), today());

        return [
            'leads_today' => (clone $soldQuery)->whereDate('distributed_at', today())->count(),
            'credit_balance' => $buyer->credit_balance,
            'total_leads' => $totalLeads,
            'spend_today' => $spendToday,
            'spend_7d' => $spend7d,
            'spend_30d' => $spend30d,
            'pending_returns' => LeadReturn::query()
                ->where('buyer_id', $buyerId)
                ->where('status', 'pending')
                ->count(),
            'converted_leads' => $convertedCount,
            'conversion_rate' => $totalLeads > 0 ? round(($convertedCount / $totalLeads) * 100, 1) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function accountSummary(Buyer $buyer): array
    {
        $account = $buyer->account;
        $caps = $buyer->caps ?? [];

        return [
            'status' => $buyer->status,
            'require_prepay' => (bool) ($account?->settings['require_buyer_prepay'] ?? false),
            'currency' => $buyer->resolvedCurrency(),
            'daily_cap' => $caps['daily'] ?? null,
            'daily_spend_cap' => $caps['daily_spend_cap'] ?? null,
            'hourly_cap' => $caps['hourly'] ?? null,
            'is_low_credit' => $this->creditAlerts->isBelowThreshold($buyer),
            'low_credit_threshold' => $this->creditAlerts->thresholdFor($buyer),
            'active_deliveries' => $buyer->deliveries()->where('status', 'active')->count(),
        ];
    }

    /**
     * @return array{labels: list<string>, leads: list<int>, spend: list<float>}
     */
    public function charts(int $buyerId): array
    {
        $labels = [];
        $leads = [];
        $spend = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = today()->subDays($i);
            $labels[] = $date->format('D');
            $leads[] = Lead::where('sold_to_buyer_id', $buyerId)->whereDate('distributed_at', $date)->count();
            $spend[] = $this->sumRevenue($buyerId, $date, $date);
        }

        return ['labels' => $labels, 'leads' => $leads, 'spend' => $spend];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatLeadRow(Lead $lead, ?BuyerFeedback $feedback = null, ?LeadReturn $return = null): array
    {
        return [
            'id' => $lead->id,
            'uuid' => $lead->uuid,
            'status' => $lead->status->value ?? $lead->status,
            'distributed_at' => $lead->distributed_at,
            'field_data' => $lead->field_data ?? [],
            'campaign' => $lead->campaign ? [
                'id' => $lead->campaign->id,
                'name' => $lead->campaign->name,
                'reference' => $lead->campaign->reference,
            ] : null,
            'financials' => $lead->financials ? [
                'revenue' => $lead->financials->revenue,
            ] : null,
            'feedback' => $feedback ? [
                'status' => $feedback->status,
                'converted' => $feedback->converted,
                'notes' => $feedback->notes,
                'recorded_at' => $feedback->updated_at,
            ] : null,
            'return_request' => $return ? [
                'status' => $return->status,
                'reason' => $return->reason,
                'submitted_at' => $return->created_at,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatLeadDetail(Lead $lead, Buyer $buyer): array
    {
        $feedback = BuyerFeedback::query()
            ->where('lead_id', $lead->id)
            ->where('buyer_id', $buyer->id)
            ->first();

        $returnRecords = LeadReturn::query()
            ->where('lead_id', $lead->id)
            ->where('buyer_id', $buyer->id)
            ->orderByDesc('created_at')
            ->get();

        $returns = $returnRecords
            ->map(fn (LeadReturn $return) => [
                'status' => $return->status,
                'reason' => $return->reason,
                'submitted_at' => $return->created_at,
            ])
            ->values()
            ->all();

        $metadata = $lead->metadata ?? [];

        return [
            ...$this->formatLeadRow($lead, $feedback, $returnRecords->first()),
            'received_at' => $lead->received_at,
            'fields' => collect($lead->field_data ?? [])->map(fn ($value, $key) => [
                'key' => $key,
                'value' => is_scalar($value) ? (string) $value : json_encode($value),
            ])->values()->all(),
            'conversion_event' => $metadata['conversion_status'] ?? null,
            'return_history' => $returns,
            'can_request_return' => ! LeadReturn::query()
                ->where('lead_id', $lead->id)
                ->where('buyer_id', $buyer->id)
                ->where('status', 'pending')
                ->exists(),
        ];
    }

    /**
     * @param  LengthAwarePaginator<int, Lead>  $paginator
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateLeads(LengthAwarePaginator $paginator, int $buyerId): LengthAwarePaginator
    {
        $leadIds = $paginator->getCollection()->pluck('id');
        $feedbackByLead = BuyerFeedback::query()
            ->where('buyer_id', $buyerId)
            ->whereIn('lead_id', $leadIds)
            ->get()
            ->keyBy('lead_id');

        $returnsByLead = LeadReturn::query()
            ->where('buyer_id', $buyerId)
            ->whereIn('lead_id', $leadIds)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('lead_id')
            ->map(fn (Collection $group) => $group->first());

        return $paginator->through(
            fn (Lead $lead) => $this->formatLeadRow(
                $lead,
                $feedbackByLead->get($lead->id),
                $returnsByLead->get($lead->id),
            )
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentActivity(int $buyerId, int $limit = 12): array
    {
        $feedback = BuyerFeedback::query()
            ->where('buyer_id', $buyerId)
            ->with('lead:id,uuid')
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (BuyerFeedback $row) => [
                'type' => 'feedback',
                'lead_uuid' => $row->lead?->uuid,
                'status' => $row->status,
                'converted' => $row->converted,
                'notes' => $row->notes,
                'at' => $row->updated_at,
            ]);

        $returns = LeadReturn::query()
            ->where('buyer_id', $buyerId)
            ->with('lead:id,uuid')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (LeadReturn $row) => [
                'type' => 'return',
                'lead_uuid' => $row->lead?->uuid,
                'status' => $row->status,
                'notes' => $row->reason,
                'at' => $row->created_at,
            ]);

        return $feedback->concat($returns)
            ->sortByDesc('at')
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @return list<array{uuid: string, label: string, email: ?string, campaign: ?string, has_feedback: bool, return_pending: bool}>
     */
    public function actionLeadOptions(int $buyerId, int $limit = 100): array
    {
        $leads = Lead::query()
            ->where('sold_to_buyer_id', $buyerId)
            ->with('campaign:id,reference')
            ->orderByDesc('distributed_at')
            ->limit($limit)
            ->get();

        $leadIds = $leads->pluck('id');
        $feedbackIds = BuyerFeedback::query()
            ->where('buyer_id', $buyerId)
            ->whereIn('lead_id', $leadIds)
            ->pluck('lead_id')
            ->flip();

        $pendingReturnIds = LeadReturn::query()
            ->where('buyer_id', $buyerId)
            ->where('status', 'pending')
            ->whereIn('lead_id', $leadIds)
            ->pluck('lead_id')
            ->flip();

        return $leads->map(function (Lead $lead) use ($feedbackIds, $pendingReturnIds) {
            $name = trim(($lead->getField('firstname') ?? '').' '.($lead->getField('lastname') ?? ''));

            return [
                'uuid' => $lead->uuid,
                'label' => $name !== '' ? $name : 'Lead',
                'email' => $lead->getField('email'),
                'campaign' => $lead->campaign?->reference,
                'has_feedback' => $feedbackIds->has($lead->id),
                'return_pending' => $pendingReturnIds->has($lead->id),
            ];
        })->values()->all();
    }

    protected function sumRevenue(int $buyerId, $from, $to): float
    {
        return (float) DB::table('lead_financials')
            ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
            ->where('leads.sold_to_buyer_id', $buyerId)
            ->whereDate('leads.distributed_at', '>=', $from)
            ->whereDate('leads.distributed_at', '<=', $to)
            ->sum('lead_financials.revenue');
    }
}
