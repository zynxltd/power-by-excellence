<?php

namespace App\Http\Controllers\Admin\ClickTrack;

use App\Http\Controllers\Controller;
use App\Models\Buyer;
use App\Models\Campaign;
use App\Models\Supplier;
use App\Models\TrackingLink;
use App\Services\ClickTrack\ClickCapService;
use App\Services\ClickTrack\ClickTrackEntitlementService;
use App\Support\Admin\ResolvesAdminAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LinkController extends Controller
{
    use ResolvesAdminAccount;

    public function index(Request $request, ClickTrackEntitlementService $entitlement, ClickCapService $caps): Response
    {
        $account = $this->resolveAdminAccount($request);
        $links = TrackingLink::with(['campaign:id,name,reference', 'supplier:id,name', 'buyer:id,name'])
            ->withCount(['clicks', 'conversions', 'impressions'])
            ->orderByDesc('updated_at')
            ->paginate(25);

        return Inertia::render('Admin/ClickTrack/Links/Index', [
            'entitlement' => $entitlement->summary($account),
            'links' => $links,
            'linkCaps' => collect($links->items())->mapWithKeys(fn (TrackingLink $link) => [
                $link->id => $caps->usageForLink($link),
            ])->all(),
            'campaigns' => Campaign::orderBy('name')->get(['id', 'name', 'reference', 'payout_amount']),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name', 'reference']),
            'buyers' => Buyer::orderBy('name')->get(['id', 'name', 'reference']),
            'goalOptions' => config('click_track.goal_options', []),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->resolveAdminAccount($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'campaign_id' => 'required|exists:campaigns,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'buyer_id' => 'nullable|exists:buyers,id',
            'destination_url' => 'required|url|max:2000',
            'goal' => 'nullable|string|max:100',
            'status' => 'required|in:active,paused',
            'payout_amount' => 'nullable|numeric|min:0',
            'revenue_amount' => 'nullable|numeric|min:0',
            'cap_hourly' => 'nullable|integer|min:0',
            'cap_daily' => 'nullable|integer|min:0',
            'cap_monthly' => 'nullable|integer|min:0',
            'conversion_cap_daily' => 'nullable|integer|min:0',
            'auto_approve_conversions' => 'boolean',
        ]);

        TrackingLink::create([
            'name' => $validated['name'],
            'campaign_id' => $validated['campaign_id'],
            'supplier_id' => $validated['supplier_id'] ?? null,
            'buyer_id' => $validated['buyer_id'] ?? null,
            'token' => Str::lower(Str::random(16)),
            'destination_url' => $validated['destination_url'],
            'goal' => $validated['goal'] ?? 'lead',
            'status' => $validated['status'],
            'payout_amount' => $validated['payout_amount'] ?? null,
            'revenue_amount' => $validated['revenue_amount'] ?? null,
            'config' => array_filter([
                'cap_hourly' => $validated['cap_hourly'] ?? null,
                'cap_daily' => $validated['cap_daily'] ?? null,
                'cap_monthly' => $validated['cap_monthly'] ?? null,
                'conversion_cap_daily' => $validated['conversion_cap_daily'] ?? null,
                'auto_approve_conversions' => $request->boolean('auto_approve_conversions', true),
            ], fn ($v) => $v !== null && $v !== ''),
        ]);

        return back()->with('success', 'Tracking link created.');
    }

    public function update(Request $request, TrackingLink $trackingLink): RedirectResponse
    {
        $this->resolveAdminAccount($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'destination_url' => 'required|url|max:2000',
            'goal' => 'nullable|string|max:100',
            'status' => 'required|in:active,paused',
            'payout_amount' => 'nullable|numeric|min:0',
            'revenue_amount' => 'nullable|numeric|min:0',
            'cap_hourly' => 'nullable|integer|min:0',
            'cap_daily' => 'nullable|integer|min:0',
            'cap_monthly' => 'nullable|integer|min:0',
            'conversion_cap_daily' => 'nullable|integer|min:0',
            'auto_approve_conversions' => 'boolean',
        ]);

        $trackingLink->update([
            'name' => $validated['name'],
            'destination_url' => $validated['destination_url'],
            'goal' => $validated['goal'] ?? $trackingLink->goal,
            'status' => $validated['status'],
            'payout_amount' => $validated['payout_amount'] ?? null,
            'revenue_amount' => $validated['revenue_amount'] ?? null,
            'config' => array_merge($trackingLink->config ?? [], array_filter([
                'cap_hourly' => $validated['cap_hourly'] ?? null,
                'cap_daily' => $validated['cap_daily'] ?? null,
                'cap_monthly' => $validated['cap_monthly'] ?? null,
                'conversion_cap_daily' => $validated['conversion_cap_daily'] ?? null,
                'auto_approve_conversions' => $request->boolean('auto_approve_conversions', true),
            ], fn ($v) => $v !== null && $v !== '')),
        ]);

        return back()->with('success', 'Tracking link updated.');
    }

    public function destroy(Request $request, TrackingLink $trackingLink): RedirectResponse
    {
        $this->resolveAdminAccount($request);
        $trackingLink->delete();

        return back()->with('success', 'Tracking link deleted.');
    }
}
