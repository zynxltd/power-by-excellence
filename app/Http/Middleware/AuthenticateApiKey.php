<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use App\Support\Tenancy\AccountContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function handle(Request $request, Closure $next, string $permission = 'leads.create'): Response
    {
        $token = $request->bearerToken() ?? $request->header('X-API-Key');

        if (! $token || ! str_contains($token, '|')) {
            return response()->json(['error' => 'Invalid API key'], 401);
        }

        [$prefix, $secret] = explode('|', $token, 2);

        $apiKey = ApiKey::withoutGlobalScopes()
            ->where('key_prefix', $prefix)
            ->where('is_active', true)
            ->first();

        if (! $apiKey || ! Hash::check($secret, $apiKey->key_hash)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $permissions = $apiKey->permissions ?? [];
        if (! in_array($permission, $permissions, true) && ! in_array('*', $permissions, true)) {
            return response()->json(['error' => 'Insufficient permissions'], 403);
        }

        $apiKey->update(['last_used_at' => now()]);
        AccountContext::set($apiKey->account);
        $request->attributes->set('api_key', $apiKey);
        $request->attributes->set('account', $apiKey->account);

        $billing = app(\App\Services\Billing\AccountBillingService::class);
        if (! $billing->canAcceptLeads($apiKey->account)) {
            return response()->json([
                'error' => 'Account billing is locked. API access suspended.',
                'billing_status' => $billing->resolveStatus($apiKey->account),
            ], 402);
        }

        return $next($request);
    }
}
