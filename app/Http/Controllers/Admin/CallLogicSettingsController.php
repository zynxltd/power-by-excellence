<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\ResolvesAdminAccount;
use App\Support\Products\CallLogicProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CallLogicSettingsController extends Controller
{
    use ResolvesAdminAccount;

    public function edit(Request $request): Response
    {
        $account = $this->resolveAdminAccount($request);

        return Inertia::render('Admin/CallLogic/Settings', [
            'enabled' => CallLogicProduct::isEnabled($account),
            'settings' => CallLogicProduct::settings($account),
            'webhookUrl' => url('/webhooks/twilio/voice/'.$account->slug),
            'sdkUrl' => url('/sdk/pbe-calls.js'),
            'telephonyProvider' => config('telephony.provider'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $account = $this->resolveAdminAccount($request);

        $validated = $request->validate([
            'enabled' => 'boolean',
            'max_tracking_numbers' => 'nullable|integer|min:1|max:1000',
            'recording_enabled' => 'boolean',
            'concurrent_call_cap' => 'nullable|integer|min:1|max:10000',
        ]);

        if ($validated['enabled'] ?? false) {
            CallLogicProduct::enable($account);
        } else {
            CallLogicProduct::disable($account);
        }

        $account->refresh();
        $settings = $account->settings ?? [];
        $settings['call_logic'] = array_merge(
            CallLogicProduct::settings($account),
            array_filter([
                'max_tracking_numbers' => $validated['max_tracking_numbers'] ?? null,
                'recording_enabled' => $validated['recording_enabled'] ?? null,
                'concurrent_call_cap' => $validated['concurrent_call_cap'] ?? null,
            ], fn ($v) => $v !== null),
        );
        $account->update(['settings' => $settings]);

        return back()->with('success', 'Call Logic settings updated.');
    }
}
