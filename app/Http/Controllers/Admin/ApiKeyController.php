<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Services\Api\ApiKeyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ApiKeyController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/ApiKeys/Index', [
            'apiKeys' => ApiKey::with('supplier')->orderByDesc('created_at')->get(),
            'suppliers' => \App\Models\Supplier::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, ApiKeyService $service): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:administrator,supplier',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'permissions' => 'nullable|array',
        ]);

        $result = $service->create([
            'account_id' => $request->user()->account_id ?? \App\Support\Tenancy\AccountContext::id(),
            'name' => $validated['name'],
            'type' => $validated['type'],
            'supplier_id' => $validated['supplier_id'] ?? null,
            'permissions' => $validated['permissions'] ?? ['leads.create', 'leads.read'],
        ]);

        $account = $request->user()?->resolveAccount();
        if ($account) {
            app(\App\Services\Platform\PlatformNotificationService::class)->logTenantActivity(
                $account,
                $request->user(),
                'api_key.created',
                'API key created',
                "API key \"{$validated['name']}\" ({$validated['type']}) was created.",
                ['api_key_name' => $validated['name'], 'type' => $validated['type']]
            );
        }

        return back()->with('success', 'API key created. Token (copy now): '.$result['token']);
    }

    public function destroy(ApiKey $apiKey): RedirectResponse
    {
        $apiKey->delete();

        return back()->with('success', 'API key revoked.');
    }
}
