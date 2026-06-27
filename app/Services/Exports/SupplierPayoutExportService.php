<?php

namespace App\Services\Exports;

use App\Models\Lead;
use App\Models\Supplier;
use App\Support\CsvExport;
use Illuminate\Http\Request;

class SupplierPayoutExportService
{
    public function buildCsv(Supplier $supplier, Request $request, int $limit = 5000): string
    {
        $query = Lead::where('supplier_id', $supplier->id)
            ->where('status', 'sold')
            ->with(['financials', 'campaign']);

        if ($request->filled('from_date')) {
            $query->whereDate('distributed_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('distributed_at', '<=', $request->to_date);
        }

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->integer('campaign_id'));
        }

        if ($request->filled('sid')) {
            $query->where('sid', $request->sid);
        }

        $leads = $query->orderByDesc('distributed_at')->limit($limit)->get();

        $csv = "uuid,campaign,sid,payout,distributed_at\n";

        foreach ($leads as $lead) {
            $csv .= CsvExport::escapeRow([
                $lead->uuid,
                $lead->campaign?->reference ?? '',
                $lead->sid,
                $lead->financials?->payout ?? 0,
                $lead->distributed_at,
            ])."\n";
        }

        return $csv;
    }
}
