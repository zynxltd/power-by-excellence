<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Support\Admin\ResolvesAdminAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CallCampaignSettingsController extends Controller
{
    use ResolvesAdminAccount;

    public function update(Request $request, Campaign $campaign): RedirectResponse
    {
        $account = $this->resolveAdminAccount($request);
        abort_unless($campaign->account_id === $account->id, 403);

        $validated = $request->validate([
            'channel' => ['required', 'string', 'in:lead,call,hybrid'],
            'call_settings' => 'nullable|array',
            'call_settings.routing_mode' => 'nullable|in:waterfall,parallel_auction',
            'call_settings.min_duration_seconds' => 'nullable|integer|min:0|max:3600',
            'call_settings.recording_enabled' => 'nullable|boolean',
            'call_settings.fallback_campaign_id' => 'nullable|integer|exists:campaigns,id',
        ]);

        if ($fallbackId = $validated['call_settings']['fallback_campaign_id'] ?? null) {
            $fallback = Campaign::find($fallbackId);
            abort_unless($fallback && $fallback->account_id === $account->id, 403);
        }

        $campaign->update([
            'channel' => $validated['channel'],
            'call_settings' => array_merge($campaign->call_settings ?? [], $validated['call_settings'] ?? []),
        ]);

        return back()->with('success', 'Call campaign settings updated.');
    }
}
