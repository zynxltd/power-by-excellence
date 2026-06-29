<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\TrackingNumber;
use App\Services\Telephony\TelephonyManager;
use App\Services\Telephony\TelephonyWebhookUrls;
use App\Support\Admin\ResolvesAdminAccount;
use App\Support\Products\CallLogicProduct;
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

        return Inertia::render('Admin/CallLogic/TrackingNumbers/Index', [
            'numbers' => TrackingNumber::with('campaign:id,name,reference')->orderByDesc('created_at')->paginate(25),
            'campaigns' => Campaign::whereIn('channel', ['call', 'hybrid'])->orderBy('name')->get(['id', 'name', 'reference']),
            'productSettings' => CallLogicProduct::settings($account),
            'searchResults' => $request->session()->get('number_search_results', []),
            'searchMeta' => $request->session()->get('number_search_meta', []),
            'defaultCountry' => config('telephony.default_country', 'GB'),
        ]);
    }

    public function search(Request $request, TelephonyManager $telephony): RedirectResponse
    {
        $this->resolveAdminAccount($request);

        $validated = $request->validate([
            'area_code' => 'required|string|max:8',
            'country' => 'nullable|string|size:2',
        ]);

        $country = strtoupper($validated['country'] ?? config('telephony.default_country', 'GB'));
        $provider = config('telephony.provider', 'log');
        $results = $telephony->gateway($provider)->searchAvailableNumbers($validated['area_code'], $country);

        return back()
            ->with('number_search_results', $results)
            ->with('number_search_meta', [
                'area_code' => $validated['area_code'],
                'country' => $country,
                'provider' => $provider,
            ]);
    }

    public function purchase(Request $request, TelephonyManager $telephony): RedirectResponse
    {
        $account = $this->resolveAdminAccount($request);

        $validated = $request->validate([
            'phone_number' => 'required|string|max:32',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'friendly_name' => 'nullable|string|max:255',
            'dni_pool' => 'nullable|string|max:64',
        ]);

        if ($validated['campaign_id'] ?? null) {
            $campaign = Campaign::findOrFail($validated['campaign_id']);
            abort_unless($campaign->account_id === $account->id, 403);
        }

        $provider = config('telephony.provider', 'log');
        $webhooks = TelephonyWebhookUrls::forAccount($account);
        $purchased = $telephony->gateway($provider)->purchaseNumber($validated['phone_number'], $webhooks);

        TrackingNumber::create([
            'account_id' => $account->id,
            'campaign_id' => $validated['campaign_id'] ?? null,
            'phone_number' => $purchased['phone_number'],
            'friendly_name' => $validated['friendly_name'] ?? null,
            'provider' => $provider,
            'provider_sid' => $purchased['sid'],
            'webhook_status' => $purchased['webhook_status'] ?? 'configured',
            'dni_pool' => $validated['dni_pool'] ?? null,
            'status' => 'active',
            'metadata' => ['webhooks' => $webhooks],
        ]);

        return redirect()->route('call-logic.tracking-numbers.index')
            ->with('success', 'Number purchased and webhooks configured.');
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

        if ($validated['phone_number'] ?? null) {
            return $this->purchase($request->merge(['phone_number' => $validated['phone_number']]), $telephony);
        }

        if ($validated['campaign_id'] ?? null) {
            $campaign = Campaign::findOrFail($validated['campaign_id']);
            abort_unless($campaign->account_id === $account->id, 403);
        }

        $provider = config('telephony.provider', 'log');
        $webhooks = TelephonyWebhookUrls::forAccount($account);
        $provisioned = $telephony->gateway($provider)->provisionNumber($validated['area_code'] ?? '020');

        TrackingNumber::create([
            'account_id' => $account->id,
            'campaign_id' => $validated['campaign_id'] ?? null,
            'phone_number' => $provisioned['phone_number'],
            'friendly_name' => $validated['friendly_name'] ?? null,
            'provider' => $provider,
            'provider_sid' => $provisioned['sid'],
            'webhook_status' => ($webhooks['voice_url'] ?? null) ? 'configured' : ($provisioned['webhook_status'] ?? 'pending'),
            'dni_pool' => $validated['dni_pool'] ?? null,
            'status' => 'active',
            'metadata' => ['webhooks' => $webhooks],
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
