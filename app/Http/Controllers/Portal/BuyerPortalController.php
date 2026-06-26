<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\BuyerFeedback;
use App\Models\Lead;
use App\Models\LeadReturn;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BuyerPortalController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $buyer = $request->user()->buyer;
        abort_unless($buyer, 403, 'Buyer account not linked to this user.');

        $stats = [
            'leads_today' => Lead::where('sold_to_buyer_id', $buyer->id)->whereDate('distributed_at', today())->count(),
            'credit_balance' => $buyer->credit_balance,
            'total_leads' => Lead::where('sold_to_buyer_id', $buyer->id)->count(),
        ];

        $recentLeads = Lead::where('sold_to_buyer_id', $buyer->id)
            ->with('financials')
            ->orderByDesc('distributed_at')
            ->limit(10)
            ->get();

        return Inertia::render('Portal/Buyer/Dashboard', [
            'buyer' => $buyer,
            'stats' => $stats,
            'recentLeads' => $recentLeads,
            'charts' => $this->buyerCharts($buyer->id),
            'currency' => $request->user()->account?->default_currency ?? 'GBP',
        ]);
    }

    protected function buyerCharts(int $buyerId): array
    {
        $labels = [];
        $leads = [];
        $spend = [];

        for ($i = 6; $i >= 0; $i--) {
            $date = today()->subDays($i);
            $labels[] = $date->format('D');
            $leads[] = Lead::where('sold_to_buyer_id', $buyerId)->whereDate('distributed_at', $date)->count();
            $spend[] = (float) DB::table('lead_financials')
                ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
                ->where('leads.sold_to_buyer_id', $buyerId)
                ->whereDate('leads.distributed_at', $date)
                ->sum('lead_financials.revenue');
        }

        return ['labels' => $labels, 'leads' => $leads, 'spend' => $spend];
    }

    public function leads(Request $request): Response
    {
        $buyer = $request->user()->buyer;
        abort_unless($buyer, 403, 'Buyer account not linked to this user.');

        $query = Lead::where('sold_to_buyer_id', $buyer->id)
            ->with(['campaign', 'financials']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->integer('campaign_id'));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('uuid', 'like', "%{$search}%")
                    ->orWhere('field_data->email', 'like', "%{$search}%")
                    ->orWhere('field_data->firstname', 'like', "%{$search}%")
                    ->orWhere('field_data->lastname', 'like', "%{$search}%");
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('distributed_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('distributed_at', '<=', $request->to_date);
        }

        $leads = $query->orderByDesc('distributed_at')->paginate(25)->withQueryString();

        $campaigns = Lead::where('sold_to_buyer_id', $buyer->id)
            ->join('campaigns', 'campaigns.id', '=', 'leads.campaign_id')
            ->distinct()
            ->orderBy('campaigns.name')
            ->get(['campaigns.id', 'campaigns.name', 'campaigns.reference']);

        return Inertia::render('Portal/Buyer/Leads', [
            'leads' => $leads,
            'filters' => $request->only(['status', 'campaign_id', 'search', 'from_date', 'to_date']),
            'campaigns' => $campaigns,
            'statuses' => ['sold', 'pending', 'processing', 'unsold', 'rejected'],
        ]);
    }

    public function downloadLeads(Request $request)
    {
        $buyer = $request->user()->buyer;
        abort_unless($buyer, 403, 'Buyer account not linked to this user.');

        $query = Lead::where('sold_to_buyer_id', $buyer->id);

        if ($request->filled('from_date')) {
            $query->whereDate('distributed_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('distributed_at', '<=', $request->to_date);
        }

        $leads = $query->orderByDesc('distributed_at')->limit(5000)->get();

        $csv = "uuid,firstname,lastname,email,phone1,zipcode,status,revenue,received_at,distributed_at\n";
        foreach ($leads as $lead) {
            $csv .= CsvExport::escapeRow([
                $lead->uuid,
                $lead->getField('firstname'),
                $lead->getField('lastname'),
                $lead->getField('email'),
                $lead->getField('phone1'),
                $lead->getField('zipcode'),
                $lead->status->value,
                $lead->financials?->revenue ?? 0,
                $lead->received_at,
                $lead->distributed_at,
            ])."\n";
        }

        return CsvExport::download($csv, 'leads.csv');
    }

    public function feedback(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lead_uuid' => 'required|string',
            'status' => 'required|string',
            'converted' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        $buyer = $request->user()->buyer;
        $lead = Lead::where('uuid', $validated['lead_uuid'])
            ->where('sold_to_buyer_id', $buyer->id)
            ->firstOrFail();

        app(\App\Services\Buyers\BuyerConversionService::class)->recordFeedback(
            $buyer,
            $lead,
            $validated['status'],
            $validated['converted'] ?? false,
            $validated['notes'] ?? null,
        );

        return back()->with('success', 'Feedback submitted.');
    }

    public function returnLead(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lead_uuid' => 'required|string',
            'reason' => 'required|string|max:500',
        ]);

        $buyer = $request->user()->buyer;
        $lead = Lead::where('uuid', $validated['lead_uuid'])
            ->where('sold_to_buyer_id', $buyer->id)
            ->firstOrFail();

        LeadReturn::create([
            'lead_id' => $lead->id,
            'buyer_id' => $buyer->id,
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        return back()->with('success', 'Return submitted for review.');
    }

    public function transactions(Request $request): Response
    {
        return $this->billing($request);
    }

    public function billing(Request $request): Response
    {
        $buyer = $request->user()->buyer;
        abort_unless($buyer, 403, 'Buyer account not linked to this user.');

        $account = $request->user()->account ?? $buyer->account;
        $requirePrepay = $account?->settings['require_buyer_prepay'] ?? false;

        return Inertia::render('Portal/Buyer/Billing', [
            'buyer' => $buyer,
            'requirePrepay' => $requirePrepay,
            'currency' => $account?->default_currency ?? 'GBP',
            'transactions' => $buyer->transactions()->orderByDesc('created_at')->paginate(25),
        ]);
    }
}
