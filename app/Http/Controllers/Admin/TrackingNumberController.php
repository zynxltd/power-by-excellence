<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\TrackingNumber;
use App\Services\Telephony\TelephonyManager;
use App\Support\Admin\ResolvesAdminAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TrackingNumberController extends Controller
{
    use ResolvesAdminAccount;

    public function index(Request $request): Response
    {
        $account = $this->resolveAdminAccount($request);

        $numbers = TrackingNumber::with('campaign:id,name,reference')
            ->orderByDesc('created_at')
            ->paginate(25);

        return Inertia::render('Admin/CallLogic/TrackingNumbers/Index', [
            'numbers' => $numbers,
            'campaigns' => Campaign::whereIn('channel', ['call', 'hybrid'])
                ->orderBy('name')
                ->get(['id', 'name', 'reference']),
            'productSettings' => \App\Support\Products\CallLogicProduct::settings($account),
        ]);
    }

    public function store(Request $request, TelephonyManager $telephony): RedirectResponse
    {
        $account = $this->resolveAdminAccount($request);

        $validated = $request->validate([
            'campaign_id' => 'nullable|exists:campaigns,id',
            'friendly_name' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:32',
            'dni_pool' => 'nullable|string|max:64',
            'area_code' => 'nullable|string|max:8',
        ]);

        if ($validated['campaign_id'] ?? null) {
            $campaign = Campaign::findOrFail($validated['campaign_id']);
            abort_unless($campaign->account_id === $account->id, 403);
        }

        $provider = config('telephony.provider', 'log');
        $phoneNumber = $validated['phone_number'] ?? null;

        if (! $phoneNumber) {
            $provisioned = $telephony->gateway($provider)->provisionNumber($validated['area_code'] ?? '020');
            $phoneNumber = $provisioned['phone_number'];
            $providerSid = $provisioned['sid'];
        } else {
            $providerSid = null;
        }

        TrackingNumber::create([
            'account_id' => $account->id,
            'campaign_id' => $validated['campaign_id'] ?? null,
            'phone_number' => $phoneNumber,
            'friendly_name' => $validated['friendly_name'] ?? null,
            'provider' => $provider,
            'provider_sid' => $providerSid ?? null,
            'dni_pool' => $validated['dni_pool'] ?? null,
            'status' => 'active',
        ]);

        return back()->with('success', 'Tracking number added.');
    }

    public function destroy(TrackingNumber $trackingNumber, TelephonyManager $telephony): RedirectResponse
    {
        if ($trackingNumber->provider_sid) {
            $telephony->gateway($trackingNumber->provider)->releaseNumber($trackingNumber->provider_sid);
        }

        $trackingNumber->update(['status' => 'released']);

        return back()->with('success', 'Tracking number released.');
    }
}
