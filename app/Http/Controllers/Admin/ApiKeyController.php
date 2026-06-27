<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Campaign;
use App\Models\Supplier;
use App\Services\Api\ApiKeyService;
use App\Services\Platform\PlatformNotificationService;
use App\Support\Admin\ResolvesAdminAccount;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ApiKeyController extends Controller
{
    use ResolvesAdminAccount;

    protected const PERMISSIONS = [
        'leads.create',
        'leads.read',
        'reports.read',
        'platform.read',
        'quarantine.manage',
        'buyers.manage',
        '*',
    ];

    public function index(Request $request): Response
    {
        $account = $this->resolveAdminAccount($request);
        $keysQuery = ApiKey::with('supplier:id,name')
            ->where('account_id', $account->id);

        return Inertia::render('Admin/ApiKeys/Index', [
            'apiKeys' => (clone $keysQuery)->orderByDesc('created_at')->get(),
            'suppliers' => Supplier::where('account_id', $account->id)
                ->orderBy('name')
                ->get(['id', 'name', 'reference']),
            'apiBaseUrl' => TenantResolver::apiBaseUrl($account),
            'campaigns' => Campaign::where('account_id', $account->id)
                ->orderBy('name')
                ->get(['id', 'name', 'reference']),
            'stats' => [
                'total' => (clone $keysQuery)->count(),
                'administrator' => (clone $keysQuery)->where('type', 'administrator')->count(),
                'supplier' => (clone $keysQuery)->where('type', 'supplier')->count(),
            ],
        ]);
    }

    public function store(Request $request, ApiKeyService $service): RedirectResponse
    {
        $account = $this->resolveAdminAccount($request);

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

        app(PlatformNotificationService::class)->logTenantActivity(
            $account,
            $request->user(),
            'api_key.created',
            'API key created',
            "API key \"{$validated['name']}\" ({$validated['type']}) was created.",
            ['api_key_name' => $validated['name'], 'type' => $validated['type']]
        );

        return back()
            ->with('success', 'API key created. Copy the token below - it will not be shown again.')
            ->with('api_token', $result['token']);
    }

    public function destroy(ApiKey $apiKey): RedirectResponse
    {
        $apiKey->delete();

        return back()->with('success', 'API key revoked.');
    }
}
