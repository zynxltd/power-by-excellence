<?php

namespace App\Http\Controllers\Api;

use App\Enums\LeadStatus;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessLeadJob;
use App\Models\Lead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function leads(Request $request): JsonResponse
    {
        $from = $request->date('from', now()->subDays(7));
        $to = $request->date('to', now());

        $stats = Lead::query()
            ->whereBetween('received_at', [$from, $to])
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'by_status' => $stats,
            'total' => $stats->sum(),
        ]);
    }

    public function revenue(Request $request): JsonResponse
    {
        $from = $request->date('from', now()->subDays(7));
        $to = $request->date('to', now());

        $totals = DB::table('lead_financials')
            ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
            ->whereBetween('leads.distributed_at', [$from, $to])
            ->selectRaw('sum(revenue) as revenue, sum(payout) as payout, sum(margin) as margin')
            ->first();

        return response()->json([
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'revenue' => (float) ($totals->revenue ?? 0),
            'payout' => (float) ($totals->payout ?? 0),
            'margin' => (float) ($totals->margin ?? 0),
        ]);
    }
}
