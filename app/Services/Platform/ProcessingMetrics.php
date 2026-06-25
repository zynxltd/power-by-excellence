<?php

namespace App\Services\Platform;

use App\Models\Lead;
use Illuminate\Support\Facades\DB;

class ProcessingMetrics
{
    public function targetMs(): int
    {
        return (int) config('performance.target_processing_ms', 200);
    }

    public function avgProcessingMs(?int $accountId = null, int $hours = 24): float
    {
        $query = Lead::withoutGlobalScopes()
            ->where('received_at', '>=', now()->subHours($hours))
            ->whereNotNull('processing_ms');

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $avg = $query->avg('processing_ms');

        if ($avg !== null) {
            return round((float) $avg, 1);
        }

        return $this->avgFromPipelineEvents($accountId, $hours);
    }

    public function p95ProcessingMs(?int $accountId = null, int $hours = 24): float
    {
        $query = Lead::withoutGlobalScopes()
            ->where('received_at', '>=', now()->subHours($hours))
            ->whereNotNull('processing_ms')
            ->orderBy('processing_ms');

        if ($accountId) {
            $query->where('account_id', $accountId);
        }

        $values = $query->pluck('processing_ms');

        if ($values->isEmpty()) {
            return 0;
        }

        $index = (int) ceil($values->count() * 0.95) - 1;

        return (float) $values->get(max(0, $index), 0);
    }

    public function withinTarget(?int $accountId = null, int $hours = 24): bool
    {
        $avg = $this->avgProcessingMs($accountId, $hours);

        return $avg === 0.0 || $avg <= $this->targetMs();
    }

    protected function avgFromPipelineEvents(?int $accountId, int $hours): float
    {
        $query = DB::table('lead_events')
            ->join('leads', 'leads.id', '=', 'lead_events.lead_id')
            ->where('lead_events.event_type', 'pipeline.completed')
            ->where('lead_events.created_at', '>=', now()->subHours($hours));

        if ($accountId) {
            $query->where('leads.account_id', $accountId);
        }

        $durations = $query
            ->get(['lead_events.payload'])
            ->map(fn ($row) => json_decode($row->payload ?? '{}', true)['duration_ms'] ?? null)
            ->filter(fn ($ms) => is_numeric($ms))
            ->map(fn ($ms) => (float) $ms);

        if ($durations->isEmpty()) {
            return 0;
        }

        return round($durations->avg(), 1);
    }
}
