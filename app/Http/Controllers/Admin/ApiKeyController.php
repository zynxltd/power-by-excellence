<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ApiKey;
use App\Models\Supplier;
use App\Services\Api\ApiKeyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ApiKeyController extends Controller
{
    protected const PERMISSIONS = [
        'leads.create',
        'leads.read',
        'reports.read',
        'quarantine.manage',
        'buyers.manage',
        '*',
    ];

    public function index(Request $request): Response
    {
        $account = $this->resolveAccount($request);

        return Inertia::render('Admin/ApiKeys/Index', [
            'apiKeys' => ApiKey::with('supplier:id,name')
                ->where('account_id', $account->id)
                ->orderByDesc('created_at')
                ->get(),
            'suppliers' => Supplier::where('account_id', $account->id)
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, ApiKeyService $service): RedirectResponse
    {
        $account = $this->resolveAccount($request);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:administrator,supplier',
            'supplier_id' => [
                'nullable',
                'required_if:type,supplier',
                Rule::exists('suppliers', 'id')->where('account_id', $account->id),
            ],
            'permissions' => 'nullable|array',
            'permissions.*' => Rule::in(self::PERMISSIONS),
        ]);

        $permissions = $validated['permissions']
            ?? ($validated['type'] === 'administrator'
                ? ['*']
                : ['leads.create', 'leads.read']);

        $result = $service->create([
            'account_id' => $account->id,
            'name' => $validated['name'],
            'type' => $validated['type'],
            'supplier_id' => $validated['type'] === 'supplier' ? $validated['supplier_id'] : null,
            'permissions' => $permissions,
        ]);

        app(\App\Services\Platform\PlatformNotificationService::class)->logTenantActivity(
            $account,
            $request->user(),
            'api_key.created',
            'API key created',
            "API key \"{$validated['name']}\" ({$validated['type']}) was created.",
            ['api_key_name' => $validated['name'], 'type' => $validated['type']]
        );

        return back()->with('success', 'API key created. Token (copy now): '.$result['token']);
    }

    public function destroy(ApiKey $apiKey): RedirectResponse
    {
        $apiKey->delete();

        return back()->with('success', 'API key revoked.');
    }

    protected function resolveAccount(Request $request): Account
    {
        $account = $request->attributes->get('account')
            ?? $request->attributes->get('host_account');

        if ($account instanceof Account) {
            return $account;
        }

        $user = $request->user();

        if ($user?->isSuperAdmin() && $request->session()->has('current_account_id')) {
            return Account::findOrFail($request->session()->get('current_account_id'));
        }

        $account = $user?->resolveAccount();
        abort_unless($account, 403, 'Select a partner platform to manage API keys.');

        return $account;
    }
}
