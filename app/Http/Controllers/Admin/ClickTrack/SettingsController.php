<?php

namespace App\Http\Controllers\Admin\ClickTrack;

use App\Http\Controllers\Controller;
use App\ClickTrack\IntegrationManifest;
use App\Services\ClickTrack\ClickCapService;
use App\Services\ClickTrack\ClickTrackEntitlementService;
use App\Support\Admin\ResolvesAdminAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    use ResolvesAdminAccount;

    public function edit(Request $request, ClickTrackEntitlementService $entitlement, ClickCapService $caps): Response
    {
        $account = $this->resolveAdminAccount($request);
        $settings = $account->settings['click_track'] ?? [];

        return Inertia::render('Admin/ClickTrack/Settings', [
            'entitlement' => $entitlement->summary($account),
            'capUsage' => $caps->accountUsage($account),
            'settings' => [
                'enabled' => (bool) ($settings['enabled'] ?? false),
                'clicks_cap' => $settings['clicks_cap'] ?? null,
                'conversions_cap' => $settings['conversions_cap'] ?? null,
                'cap_hourly' => $settings['cap_hourly'] ?? null,
                'cap_soft_limit_pct' => $settings['cap_soft_limit_pct'] ?? config('click_track.cap_soft_limit_pct', 80),
                'fraud_block_duplicates' => (bool) ($settings['fraud_block_duplicates'] ?? config('click_track.fraud.block_duplicates', true)),
                'fraud_duplicate_window_minutes' => $settings['fraud_duplicate_window_minutes'] ?? config('click_track.fraud.duplicate_window_minutes', 60),
            ],
            'plans' => config('click_track.plans', []),
            'pricingModuleFlags' => IntegrationManifest::pricingModuleFlags(),
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
            'cap_hourly' => 'nullable|integer|min:0',
            'cap_soft_limit_pct' => 'nullable|integer|min:50|max:99',
            'fraud_block_duplicates' => 'boolean',
            'fraud_duplicate_window_minutes' => 'nullable|integer|min:1|max:1440',
        ]);

        $settings = $account->settings ?? [];
        $clickTrack = $settings['click_track'] ?? [];
        $clickTrack['enabled'] = $request->boolean('enabled');
        $clickTrack['clicks_cap'] = $validated['clicks_cap'] ?? null;
        $clickTrack['conversions_cap'] = $validated['conversions_cap'] ?? null;
        $clickTrack['cap_hourly'] = $validated['cap_hourly'] ?? null;
        $clickTrack['cap_soft_limit_pct'] = $validated['cap_soft_limit_pct'] ?? config('click_track.cap_soft_limit_pct', 80);
        $clickTrack['fraud_block_duplicates'] = $request->boolean('fraud_block_duplicates', true);
        $clickTrack['fraud_duplicate_window_minutes'] = $validated['fraud_duplicate_window_minutes'] ?? config('click_track.fraud.duplicate_window_minutes', 60);
        if (! isset($clickTrack['usage_period_start'])) {
            $clickTrack['usage_period_start'] = now()->startOfMonth()->toDateString();
        }
        $settings['click_track'] = $clickTrack;
        $account->update(['settings' => $settings]);

        return back()->with('success', 'Click Track settings saved.');
    }
}
