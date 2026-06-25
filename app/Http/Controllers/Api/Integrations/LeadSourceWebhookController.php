<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LeadSourceWebhookController extends Controller
{
    public function verify(Request $request, string $provider, string $accountSlug): JsonResponse|string
    {
        $account = $this->resolveAccount($accountSlug);
        $config = $account->settings['lead_sources'][$provider] ?? [];

        if ($provider === 'facebook' && $request->has('hub_challenge')) {
            $token = $request->query('hub_verify_token');
            if ($token && $token === ($config['verify_token'] ?? null)) {
                return response($request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
            }

            abort(403);
        }

        return response()->json(['status' => 'ok', 'provider' => $provider]);
    }

    public function ingest(Request $request, string $provider, string $accountSlug): JsonResponse
    {
        $account = $this->resolveAccount($accountSlug);
        $config = $account->settings['lead_sources'][$provider] ?? [];

        if (! ($config['enabled'] ?? false)) {
            return response()->json(['error' => 'Integration disabled'], 403);
        }

        Log::info('Lead source webhook received', [
            'provider' => $provider,
            'account_id' => $account->id,
            'payload_keys' => array_keys($request->all()),
        ]);

        return response()->json([
            'accepted' => true,
            'provider' => $provider,
            'message' => 'Lead queued for processing (demo ingest — map fields in integration settings).',
            'campaign_id' => $config['campaign_id'] ?? null,
        ], 202);
    }

    protected function resolveAccount(string $slug): Account
    {
        $account = Account::where('slug', $slug)->first();
        abort_unless($account, 404, 'Unknown platform.');

        return $account;
    }
}
