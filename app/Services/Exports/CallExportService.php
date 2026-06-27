<?php

namespace App\Services\Exports;

use App\Models\CallSession;
use Illuminate\Support\Carbon;

class CallExportService
{
    /**
     * @param  array{status?: string, from_date?: string, to_date?: string, campaign_id?: int}  $filters
     * @return \Generator<int, list<string|null>>
     */
    public function rowsForBuyer(int $buyerId, array $filters = []): \Generator
    {
        yield ['UUID', 'Caller', 'Campaign', 'Status', 'Duration (s)', 'Revenue', 'Disposition', 'Received at'];

        $query = CallSession::where('sold_to_buyer_id', $buyerId)
            ->with('campaign:id,name,reference')
            ->orderByDesc('created_at');

        $this->applyFilters($query, $filters);

        foreach ($query->cursor() as $call) {
            yield [
                $call->uuid,
                $call->caller_number,
                $call->campaign?->name,
                $call->status->value ?? (string) $call->status,
                (string) $call->duration_seconds,
                $call->revenue !== null ? (string) $call->revenue : '',
                $call->disposition,
                $call->created_at?->toIso8601String(),
            ];
        }
    }

    /**
     * @param  array{status?: string, from_date?: string, to_date?: string, campaign_id?: int}  $filters
     * @return \Generator<int, list<string|null>>
     */
    public function rowsForAccount(int $accountId, array $filters = []): \Generator
    {
        yield ['UUID', 'Caller', 'Campaign', 'Buyer', 'Status', 'Duration (s)', 'Revenue', 'Received at'];

        $query = CallSession::where('account_id', $accountId)
            ->with(['campaign:id,name', 'soldToBuyer:id,name'])
            ->orderByDesc('created_at');

        $this->applyFilters($query, $filters);

        foreach ($query->cursor() as $call) {
            yield [
                $call->uuid,
                $call->caller_number,
                $call->campaign?->name,
                $call->soldToBuyer?->name,
                $call->status->value ?? (string) $call->status,
                (string) $call->duration_seconds,
                $call->revenue !== null ? (string) $call->revenue : '',
                $call->created_at?->toIso8601String(),
            ];
        }
    }

    protected function applyFilters($query, array $filters): void
    {
        if ($status = $filters['status'] ?? null) {
            $query->where('status', $status);
        }

        if ($campaignId = $filters['campaign_id'] ?? null) {
            $query->where('campaign_id', $campaignId);
        }

        if ($from = $filters['from_date'] ?? null) {
            $query->where('created_at', '>=', Carbon::parse($from)->startOfDay());
        }

        if ($to = $filters['to_date'] ?? null) {
            $query->where('created_at', '<=', Carbon::parse($to)->endOfDay());
        }
    }
}
