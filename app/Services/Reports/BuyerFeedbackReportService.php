<?php

namespace App\Services\Reports;

use App\Models\Buyer;
use App\Models\BuyerFeedback;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Supplier;
use App\Support\Tenancy\AccountContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class BuyerFeedbackReportService
{
    /** @var list<string> */
    public const INVALID_STATUSES = ['invalid', 'bad_lead', 'returned'];

    /** @var list<string> */
    public const POSITIVE_STATUSES = ['converted', 'funded', 'sale', 'closed'];

    public function baseQuery(?int $accountId = null): Builder
    {
        return BuyerFeedback::query()
            ->whereHas('lead', function (Builder $query) use ($accountId) {
                if ($accountId) {
                    $query->where('account_id', $accountId);
                }
            });
    }

    /**
     * @return array{
     *     total: int,
     *     invalid: int,
     *     converted: int,
     *     contacted: int,
     *     with_notes: int,
     *     invalid_rate: float|null
     * }
     */
    public function summary(Builder $query): array
    {
        $total = (clone $query)->count();
        $invalid = (clone $query)->whereIn('buyer_feedback.status', self::INVALID_STATUSES)->count();
        $converted = (clone $query)->where(function (Builder $q) {
            $q->where('buyer_feedback.converted', true)
                ->orWhereIn('buyer_feedback.status', self::POSITIVE_STATUSES);
        })->count();
        $contacted = (clone $query)->whereIn('buyer_feedback.status', ['contacted', 'called', 'callback', 'spoken', 'contact'])->count();
        $withNotes = (clone $query)->whereNotNull('buyer_feedback.notes')->where('buyer_feedback.notes', '!=', '')->count();

        return [
            'total' => $total,
            'invalid' => $invalid,
            'converted' => $converted,
            'contacted' => $contacted,
            'with_notes' => $withNotes,
            'invalid_rate' => $total > 0 ? round(($invalid / $total) * 100, 1) : null,
        ];
    }

    /**
     * @return list<array{id: int|null, name: string, reference: string|null, total: int, invalid: int, converted: int}>
     */
    public function breakdownBySupplier(Builder $query): array
    {
        return $this->breakdownByLeadRelation($query, 'supplier_id', Supplier::class);
    }

    /**
     * @return list<array{id: int|null, name: string, reference: string|null, total: int, invalid: int, converted: int}>
     */
    public function breakdownByCampaign(Builder $query): array
    {
        return $this->breakdownByLeadRelation($query, 'campaign_id', Campaign::class);
    }

    /**
     * @return list<array{id: int|null, name: string, reference: string|null, total: int, invalid: int, converted: int}>
     */
    public function breakdownByBuyer(Builder $query): array
    {
        $rows = (clone $query)
            ->selectRaw('buyer_feedback.buyer_id as group_id')
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when buyer_feedback.status in ('.$this->statusPlaceholders(self::INVALID_STATUSES).') then 1 else 0 end) as invalid_count')
            ->selectRaw('sum(case when buyer_feedback.converted = 1 or buyer_feedback.status in ('.$this->statusPlaceholders(self::POSITIVE_STATUSES).') then 1 else 0 end) as converted_count')
            ->groupBy('group_id')
            ->orderByDesc('total')
            ->get();

        $buyers = Buyer::whereIn('id', $rows->pluck('group_id')->filter())->get()->keyBy('id');

        return $rows->map(function ($row) use ($buyers) {
            $buyer = $buyers->get($row->group_id);

            return [
                'id' => $row->group_id,
                'name' => $buyer?->name ?? 'Unknown buyer',
                'reference' => $buyer?->reference,
                'total' => (int) $row->total,
                'invalid' => (int) $row->invalid_count,
                'converted' => (int) $row->converted_count,
            ];
        })->values()->all();
    }

    /**
     * @return list<array{sid: string|null, total: int, invalid: int}>
     */
    public function breakdownBySid(Builder $query): array
    {
        $invalidList = $this->statusPlaceholders(self::INVALID_STATUSES);

        return (clone $query)
            ->join('leads', 'buyer_feedback.lead_id', '=', 'leads.id')
            ->selectRaw("coalesce(nullif(leads.sid, ''), '—') as sid_label")
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when buyer_feedback.status in ({$invalidList}) then 1 else 0 end) as invalid_count")
            ->groupBy('sid_label')
            ->orderByDesc('total')
            ->limit(15)
            ->get()
            ->map(fn ($row) => [
                'sid' => $row->sid_label === '—' ? null : $row->sid_label,
                'total' => (int) $row->total,
                'invalid' => (int) $row->invalid_count,
            ])
            ->values()
            ->all();
    }

    public function applyFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if ($status === 'invalid') {
                $query->whereIn('buyer_feedback.status', self::INVALID_STATUSES);
            } elseif ($status === 'converted') {
                $query->where(function (Builder $q) {
                    $q->where('buyer_feedback.converted', true)
                        ->orWhereIn('buyer_feedback.status', self::POSITIVE_STATUSES);
                });
            } elseif ($status === 'contacted') {
                $query->whereIn('buyer_feedback.status', ['contacted', 'called', 'callback', 'spoken', 'contact']);
            } else {
                $query->where('buyer_feedback.status', $status);
            }
        }

        if ($request->filled('buyer_id')) {
            $query->where('buyer_feedback.buyer_id', $request->integer('buyer_id'));
        }

        if ($request->filled('campaign_id')) {
            $query->whereHas('lead', fn (Builder $q) => $q->where('campaign_id', $request->integer('campaign_id')));
        }

        if ($request->filled('supplier_id')) {
            $query->whereHas('lead', fn (Builder $q) => $q->where('supplier_id', $request->integer('supplier_id')));
        }

        if ($request->filled('sid')) {
            $sid = $request->string('sid')->toString();
            $query->whereHas('lead', fn (Builder $q) => $q->where('sid', $sid));
        }

        if ($request->filled('from_date')) {
            $query->whereDate('buyer_feedback.created_at', '>=', $request->input('from_date'));
        }

        if ($request->filled('to_date')) {
            $query->whereDate('buyer_feedback.created_at', '<=', $request->input('to_date'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function (Builder $q) use ($search) {
                $q->whereHas('lead', function (Builder $lead) use ($search) {
                    $lead->where('uuid', 'like', "%{$search}%")
                        ->orWhere('queue_id', 'like', "%{$search}%")
                        ->orWhere('sid', 'like', "%{$search}%");
                })->orWhere('notes', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    public function paginated(Builder $query, int $perPage = 25): LengthAwarePaginator
    {
        return $query
            ->with([
                'buyer:id,name,reference',
                'lead:id,uuid,queue_id,campaign_id,supplier_id,source_id,sold_to_buyer_id,sid,ssid,received_at,status',
                'lead.campaign:id,name,reference',
                'lead.supplier:id,name,reference',
                'lead.source:id,sid,name,supplier_id',
                'lead.soldToBuyer:id,name,reference',
                'lead.financials:lead_id,revenue,payout,margin,currency',
            ])
            ->orderByDesc('buyer_feedback.created_at')
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (BuyerFeedback $feedback) => $this->formatRow($feedback));
    }

    /**
     * @return array<string, mixed>
     */
    public function formatRow(BuyerFeedback $feedback): array
    {
        $lead = $feedback->lead;

        return [
            'id' => $feedback->id,
            'status' => $feedback->status,
            'converted' => $feedback->converted,
            'is_invalid' => in_array($feedback->status, self::INVALID_STATUSES, true),
            'notes' => $feedback->notes,
            'recorded_at' => $feedback->created_at?->toIso8601String(),
            'buyer' => $feedback->buyer ? [
                'id' => $feedback->buyer->id,
                'name' => $feedback->buyer->name,
                'reference' => $feedback->buyer->reference,
            ] : null,
            'lead' => $lead ? [
                'id' => $lead->id,
                'uuid' => $lead->uuid,
                'queue_id' => $lead->queue_id,
                'status' => $lead->status?->value ?? $lead->status,
                'received_at' => $lead->received_at?->toIso8601String(),
                'sid' => $lead->sid,
                'ssid' => $lead->ssid,
                'campaign' => $lead->campaign ? [
                    'id' => $lead->campaign->id,
                    'name' => $lead->campaign->name,
                    'reference' => $lead->campaign->reference,
                ] : null,
                'supplier' => $lead->supplier ? [
                    'id' => $lead->supplier->id,
                    'name' => $lead->supplier->name,
                    'reference' => $lead->supplier->reference,
                ] : null,
                'source' => $lead->source ? [
                    'id' => $lead->source->id,
                    'sid' => $lead->source->sid,
                    'name' => $lead->source->name,
                ] : null,
                'sold_to_buyer' => $lead->soldToBuyer ? [
                    'id' => $lead->soldToBuyer->id,
                    'name' => $lead->soldToBuyer->name,
                    'reference' => $lead->soldToBuyer->reference,
                ] : null,
                'revenue' => $lead->financials?->revenue,
                'currency' => $lead->financials?->currency,
            ] : null,
        ];
    }

    /**
     * @return list<array{id: int|null, name: string, reference: string|null, total: int, invalid: int, converted: int}>
     */
    protected function breakdownByLeadRelation(Builder $query, string $leadColumn, string $modelClass): array
    {
        $invalidList = $this->statusPlaceholders(self::INVALID_STATUSES);
        $positiveList = $this->statusPlaceholders(self::POSITIVE_STATUSES);

        $rows = (clone $query)
            ->join('leads', 'buyer_feedback.lead_id', '=', 'leads.id')
            ->selectRaw("leads.{$leadColumn} as group_id")
            ->selectRaw('count(*) as total')
            ->selectRaw("sum(case when buyer_feedback.status in ({$invalidList}) then 1 else 0 end) as invalid_count")
            ->selectRaw("sum(case when buyer_feedback.converted = 1 or buyer_feedback.status in ({$positiveList}) then 1 else 0 end) as converted_count")
            ->groupBy('group_id')
            ->orderByDesc('total')
            ->get();

        $entities = $modelClass::whereIn('id', $rows->pluck('group_id')->filter())->get()->keyBy('id');

        return $rows->map(function ($row) use ($entities) {
            $entity = $entities->get($row->group_id);

            return [
                'id' => $row->group_id,
                'name' => $entity?->name ?? ($row->group_id ? 'Unknown' : 'Unassigned'),
                'reference' => $entity?->reference ?? null,
                'total' => (int) $row->total,
                'invalid' => (int) $row->invalid_count,
                'converted' => (int) $row->converted_count,
            ];
        })->values()->all();
    }

    public function accountId(): ?int
    {
        return AccountContext::id();
    }

    /**
     * @param  list<string>  $statuses
     */
    protected function statusPlaceholders(array $statuses): string
    {
        return implode(',', array_map(fn ($s) => "'".addslashes($s)."'", $statuses));
    }
}
