<?php

namespace App\Http\Controllers\Admin\ClickTrack;

use App\Http\Controllers\Controller;
use App\Services\ClickTrack\ClickTrackEntitlementService;
use App\Support\Admin\ResolvesAdminAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    use ResolvesAdminAccount;

    public function edit(Request $request, ClickTrackEntitlementService $entitlement): Response
    {
        $account = $this->resolveAdminAccount($request);
        $settings = $account->settings['click_track'] ?? [];

        return Inertia::render('Admin/ClickTrack/Settings', [
            'entitlement' => $entitlement->summary($account),
            'settings' => [
                'enabled' => (bool) ($settings['enabled'] ?? false),
                'clicks_cap' => $settings['clicks_cap'] ?? null,
                'conversions_cap' => $settings['conversions_cap'] ?? null,
            ],
            'plans' => config('click_track.plans', []),
        ]);
    }

    public function update(Request $request, ClickTrackEntitlementService $entitlement): RedirectResponse
    {
        $account = $this->resolveAdminAccount($request);

        if (! $entitlement->planConfig($account)['addon_available'] && ! auth()->user()?->isSuperAdmin()) {
            return back()->with('error', 'Click Track add-on is not available on your plan.');
        }

        $validated = $request->validate([
            'enabled' => 'boolean',
            'clicks_cap' => 'nullable|integer|min:0',
            'conversions_cap' => 'nullable|integer|min:0',
        ]);

        $settings = $account->settings ?? [];
        $clickTrack = $settings['click_track'] ?? [];
        $clickTrack['enabled'] = $request->boolean('enabled');
        $clickTrack['clicks_cap'] = $validated['clicks_cap'] ?? null;
        $clickTrack['conversions_cap'] = $validated['conversions_cap'] ?? null;
        if (! isset($clickTrack['usage_period_start'])) {
            $clickTrack['usage_period_start'] = now()->startOfMonth()->toDateString();
        }
        $settings['click_track'] = $clickTrack;
        $account->update(['settings' => $settings]);

        return back()->with('success', 'Click Track settings saved.');
    }
}
