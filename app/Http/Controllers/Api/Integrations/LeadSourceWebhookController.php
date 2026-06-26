<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\Integrations\LeadSourceIngestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LeadSourceWebhookController extends Controller
{
    public function __construct(
        protected LeadSourceIngestService $ingestService,
    ) {}

    public function verify(Request $request, string $provider, string $accountSlug): JsonResponse|string
    {
        $account = $this->resolveAccount($accountSlug);
        $config = $account->settings['lead_sources'][$provider] ?? [];

        if ($provider === 'facebook' && $request->isMethod('get') && $request->has('hub_challenge')) {
            $token = $request->query('hub_verify_token');
            if ($token && hash_equals((string) ($config['verify_token'] ?? ''), (string) $token)) {
                return response($request->query('hub_challenge'), 200)->header('Content-Type', 'text/plain');
            }

            abort(403, 'Verify token mismatch.');
        }

        if ($request->isMethod('post')) {
            return $this->handleWebhookPost($request, $account, $provider, $config);
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

        try {
            $result = $this->ingestService->ingest($account, $config, $request->all(), $provider);
        } catch (\Throwable $e) {
            Log::warning('Lead source ingest failed', [
                'provider' => $provider,
                'account_id' => $account->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Ingest failed',
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'accepted' => true,
            'provider' => $provider,
            'lead_id' => $result['lead_id'],
            'queue_id' => $result['queue_id'],
            'status' => $result['status'],
        ], 202);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function handleWebhookPost(Request $request, Account $account, string $provider, array $config): JsonResponse
    {
        if (! ($config['enabled'] ?? false)) {
            return response()->json(['error' => 'Integration disabled'], 403);
        }

        $payload = $request->all();

        Log::info('Lead source webhook received', [
            'provider' => $provider,
            'account_id' => $account->id,
            'payload_keys' => array_keys($payload),
        ]);

        if ($provider === 'facebook' && ($payload['object'] ?? '') === 'page') {
            $fields = $this->ingestService->extractFields($provider, $config, $payload);

            if ($fields === [] && empty($config['page_access_token'])) {
                return response()->json([
                    'accepted' => true,
                    'message' => 'Webhook received. Add a Page access token in integration settings to fetch lead field data from Facebook.',
                ]);
            }
        }

        try {
            $result = $this->ingestService->ingest($account, $config, $payload, $provider);
        } catch (\Throwable $e) {
            Log::warning('Lead source webhook ingest failed', [
                'provider' => $provider,
                'account_id' => $account->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Webhook ingest failed',
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'accepted' => true,
            'provider' => $provider,
            'lead_id' => $result['lead_id'],
            'queue_id' => $result['queue_id'],
            'status' => $result['status'],
        ], 202);
    }

    protected function resolveAccount(string $slug): Account
    {
        $account = Account::where('slug', $slug)->first();
        abort_unless($account, 404, 'Unknown platform.');

        return $account;
    }
}
