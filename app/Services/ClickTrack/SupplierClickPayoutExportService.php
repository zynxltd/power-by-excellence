<?php

namespace App\Services\ClickTrack;

use App\Models\Supplier;
use App\Models\SupplierClickPayout;
use App\Support\CsvExport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SupplierClickPayoutExportService
{
    public function download(Supplier $supplier, Request $request): Response
    {
        $query = SupplierClickPayout::query()
            ->where('supplier_id', $supplier->id)
            ->with(['trackingConversion.trackingLink:id,name'])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $csv = "date,offer,status,payout,revenue,share_pct,approved_at\n";

        foreach ($query->limit(5000)->get() as $payout) {
            $csv .= CsvExport::escapeRow([
                $payout->created_at?->toDateTimeString(),
                $payout->trackingConversion?->trackingLink?->name,
                $payout->status,
                $payout->amount,
                $payout->revenue,
                $payout->revenue_share_pct,
                $payout->approved_at?->toDateTimeString(),
            ])."\n";
        }

        return CsvExport::download($csv, 'click-payouts.csv');
    }
}
