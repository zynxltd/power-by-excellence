<?php

namespace App\Services\Suppliers;

use App\Models\Lead;
use App\Models\Source;
use App\Models\Supplier;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SupplierPortalService
{
    /**
     * @return array<string, mixed>
     */
    public function dashboardStats(Supplier $supplier): array
    {
        $supplierId = $supplier->id;
        $baseQuery = Lead::query()->where('supplier_id', $supplierId);

        $totalSubmitted = (clone $baseQuery)->count();
        $totalSold = (clone $baseQuery)->where('status', 'sold')->count();

        return [
            'leads_today' => (clone $baseQuery)->whereDate('received_at', today())->count(),
            'sold_today' => (clone $baseQuery)->whereDate('distributed_at', today())->where('status', 'sold')->count(),
            'revenue_today' => $this->sumPayout($supplierId, today(), today()),
            'payout_7d' => $this->sumPayout($supplierId, today()->subDays(6), today()),
            'payout_30d' => $this->sumPayout($supplierId, today()->subDays(29), today()),
            'submitted_7d' => (clone $baseQuery)->whereDate('received_at', '>=', today()->subDays(6))->count(),
            'sold_7d' => (clone $baseQuery)->where('status', 'sold')->whereDate('distributed_at', '>=', today()->subDays(6))->count(),
            'total_submitted' => $totalSubmitted,
            'total_sold' => $totalSold,
            'sold_rate' => $totalSubmitted > 0 ? round(($totalSold / $totalSubmitted) * 100, 1) : null,
            'rejected_today' => (clone $baseQuery)->whereDate('received_at', today())->whereIn('status', ['rejected', 'quarantined', 'duplicate'])->count(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function accountSummary(Supplier $supplier): array
    {
        $settings = $supplier->affiliate_settings ?? [];
        $sources = $supplier->sources()->withCount('subSuppliers')->orderBy('sid')->get();

        return [
            'status' => $supplier->status,
            'reference' => $supplier->reference,
            'rev_share_percent' => $settings['rev_share_percent'] ?? null,
            'default_postback_url' => $settings['default_postback_url'] ?? null,
            'source_count' => $sources->count(),
            'sub_affiliate_count' => $sources->sum('sub_suppliers_count'),
            'sources' => $sources->map(fn ($source) => [
                'sid' => $source->sid,
                'name' => $source->name,
                'sub_suppliers_count' => $source->sub_suppliers_count,
                'daily_cap' => $source->caps['daily'] ?? null,
                'payout_override' => $source->payout_override,
            ])->values()->all(),
        ];
    }

    /**
     * @return array{labels: list<string>, leads: list<int>, sold: list<int>, payout: list<float>}
     */
    public function charts(int $supplierId): array
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
            $payout[] = $this->sumPayout($supplierId, $date, $date);
        }

        return compact('labels', 'leads', 'sold', 'payout');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sourcePerformance(int $supplierId, int $days = 30): array
    {
        $from = today()->subDays($days - 1);

        $rows = Lead::query()
            ->where('supplier_id', $supplierId)
            ->whereDate('received_at', '>=', $from)
            ->selectRaw('sid, COUNT(*) as submitted, SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as sold', ['sold'])
            ->groupBy('sid')
            ->orderByDesc('submitted')
            ->get();

        $payoutBySid = DB::table('leads')
            ->join('lead_financials', 'lead_financials.lead_id', '=', 'leads.id')
            ->where('leads.supplier_id', $supplierId)
            ->where('leads.status', 'sold')
            ->whereDate('leads.distributed_at', '>=', $from)
            ->selectRaw('leads.sid, SUM(lead_financials.payout) as payout')
            ->groupBy('leads.sid')
            ->pluck('payout', 'sid');

        return $rows->map(fn ($row) => [
            'sid' => $row->sid ?: '—',
            'submitted' => (int) $row->submitted,
            'sold' => (int) $row->sold,
            'payout' => (float) ($payoutBySid[$row->sid] ?? 0),
            'sold_rate' => $row->submitted > 0 ? round(($row->sold / $row->submitted) * 100, 1) : null,
        ])->values()->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function formatLeadRow(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'uuid' => $lead->uuid,
            'status' => $lead->status->value ?? $lead->status,
            'sid' => $lead->sid,
            'ssid' => $lead->ssid,
            'received_at' => $lead->received_at,
            'distributed_at' => $lead->distributed_at,
            'reject_reason' => $lead->reject_reason,
            'field_data' => $lead->field_data ?? [],
            'campaign' => $lead->campaign ? [
                'id' => $lead->campaign->id,
                'name' => $lead->campaign->name,
                'reference' => $lead->campaign->reference,
            ] : null,
            'financials' => $lead->financials ? [
                'payout' => $lead->financials->payout,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formatLeadDetail(Lead $lead): array
    {
        $metadata = $lead->metadata ?? [];

        $sourceRecord = $this->resolvedSourceRecord($lead);

        return [
            ...$this->formatLeadRow($lead),
            'fields' => collect($lead->field_data ?? [])->map(fn ($value, $key) => [
                'key' => $key,
                'value' => is_scalar($value) ? (string) $value : json_encode($value),
            ])->values()->all(),
            'conversion_event' => $metadata['conversion_status'] ?? null,
            'ingest_source' => $lead->getAttributes()['source'] ?? null,
            'source_record' => $sourceRecord ? [
                'sid' => $sourceRecord->sid,
                'name' => $sourceRecord->name,
            ] : null,
        ];
    }

    protected function resolvedSourceRecord(Lead $lead): ?Source
    {
        if ($lead->relationLoaded('source')) {
            $relation = $lead->getRelation('source');

            if ($relation instanceof Source) {
                return $relation;
            }
        }

        if ($lead->source_id) {
            return Source::query()->find($lead->source_id);
        }

        return null;
    }

    /**
     * @param  LengthAwarePaginator<int, Lead>  $paginator
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function paginateLeads(LengthAwarePaginator $paginator): LengthAwarePaginator
    {
        return $paginator->through(fn (Lead $lead) => $this->formatLeadRow($lead));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentActivity(int $supplierId, int $limit = 10): array
    {
        return Lead::query()
            ->where('supplier_id', $supplierId)
            ->orderByDesc('received_at')
            ->limit($limit)
            ->get()
            ->map(fn (Lead $lead) => [
                'type' => 'submission',
                'lead_uuid' => $lead->uuid,
                'status' => $lead->status->value ?? $lead->status,
                'sid' => $lead->sid,
                'payout' => $lead->financials?->payout,
                'at' => $lead->received_at,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentLeads(int $supplierId, int $limit = 10): array
    {
        return Lead::query()
            ->where('supplier_id', $supplierId)
            ->with(['campaign', 'financials'])
            ->orderByDesc('received_at')
            ->limit($limit)
            ->get()
            ->map(fn (Lead $lead) => $this->formatLeadRow($lead))
            ->all();
    }

    protected function sumPayout(int $supplierId, $from, $to): float
    {
        return (float) DB::table('lead_financials')
            ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
            ->where('leads.supplier_id', $supplierId)
            ->whereDate('leads.distributed_at', '>=', $from)
            ->whereDate('leads.distributed_at', '<=', $to)
            ->sum('lead_financials.payout');
    }
}
