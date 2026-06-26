<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\Source;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Admin\ResolvesAdminAccount;
use App\Support\Tenancy\AccountContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    use ResolvesAdminAccount;

    public function index(Request $request): Response
    {
        $this->resolveAdminAccount($request);

        $query = Supplier::query()
            ->with('sources')
            ->withCount('sources')
            ->orderBy('name');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('reference', 'like', "%{$search}%")
                    ->orWhereHas('sources', fn ($s) => $s->where('sid', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $suppliers = $query->paginate(25)->withQueryString();

        $supplierIds = $suppliers->getCollection()->pluck('id');
        $leadCounts = Lead::query()
            ->whereIn('supplier_id', $supplierIds)
            ->selectRaw('supplier_id, count(*) as total')
            ->groupBy('supplier_id')
            ->pluck('total', 'supplier_id');

        $suppliers->getCollection()->transform(function (Supplier $supplier) use ($leadCounts) {
            $supplier->leads_count = (int) ($leadCounts[$supplier->id] ?? 0);

            return $supplier;
        });

        return Inertia::render('Admin/Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => $request->only(['search', 'status']),
            'stats' => [
                'total' => Supplier::count(),
                'active' => Supplier::where('status', 'active')->count(),
                'sources' => Source::whereHas('supplier')->count(),
            ],
        ]);
    }

    public function show(Request $request, Supplier $supplier): Response
    {
        $this->resolveAdminAccountForTenant($request, $supplier->account_id);

        $supplier->load(['sources', 'sources.subSuppliers']);

        $recentLeads = Lead::where('supplier_id', $supplier->id)
            ->with(['campaign:id,name', 'financials'])
            ->orderByDesc('received_at')
            ->limit(10)
            ->get();

        $leadStats = [
            'total' => Lead::where('supplier_id', $supplier->id)->count(),
            'sold' => Lead::where('supplier_id', $supplier->id)->where('status', 'sold')->count(),
            'pending' => Lead::where('supplier_id', $supplier->id)->where('status', 'pending')->count(),
        ];

        $portalUser = User::query()
            ->where('supplier_id', $supplier->id)
            ->where('role', UserRole::SupplierPortal)
            ->first(['id', 'email', 'name']);

        return Inertia::render('Admin/Suppliers/Show', [
            'supplier' => $supplier,
            'recentLeads' => $recentLeads,
            'leadStats' => $leadStats,
            'portalUser' => $portalUser,
        ]);
    }

    public function create(Request $request): Response
    {
        $this->resolveAdminAccount($request);

        return Inertia::render('Admin/Suppliers/Form', [
            'supplier' => null,
            'portalUser' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $account = $this->resolveAdminAccount($request);
        AccountContext::set($account);
        $request->attributes->set('account', $account);

        $validated = $this->validateSupplier($request);
        $sources = $validated['sources'] ?? [];
        $portal = $this->extractPortalFields($validated);

        $supplier = Supplier::create([
            'reference' => $validated['reference'],
            'name' => $validated['name'],
            'status' => $validated['status'] ?? 'active',
            'affiliate_settings' => $this->affiliateSettingsFrom($validated),
        ]);

        $this->syncSources($supplier, $sources);
        $this->syncPortalUser($supplier, $portal);

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Supplier created.');
    }

    public function edit(Request $request, Supplier $supplier): Response
    {
        $this->resolveAdminAccountForTenant($request, $supplier->account_id);

        $supplier->load(['sources.subSuppliers']);
        $portalUser = User::query()
            ->where('supplier_id', $supplier->id)
            ->where('role', UserRole::SupplierPortal)
            ->first(['id', 'email', 'name']);

        return Inertia::render('Admin/Suppliers/Form', [
            'supplier' => $supplier,
            'portalUser' => $portalUser,
        ]);
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->resolveAdminAccountForTenant($request, $supplier->account_id);

        $validated = $this->validateSupplier($request, $supplier);
        $sources = $validated['sources'] ?? [];
        $portal = $this->extractPortalFields($validated);

        $supplier->update([
            'reference' => $validated['reference'],
            'name' => $validated['name'],
            'status' => $validated['status'] ?? 'active',
            'affiliate_settings' => $this->affiliateSettingsFrom($validated),
        ]);

        $this->syncSources($supplier, $sources);
        $this->syncPortalUser($supplier, $portal);

        return redirect()->route('suppliers.show', $supplier)->with('success', 'Supplier updated.');
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        $this->resolveAdminAccountForTenant($request, $supplier->account_id);

        $supplier->delete();

        return redirect()->route('suppliers.index')->with('success', 'Supplier deleted.');
    }

    protected function validateSupplier(Request $request, ?Supplier $supplier = null): array
    {
        $request->merge([
            'reference' => strtolower(trim((string) $request->input('reference', ''))),
        ]);

        $accountId = AccountContext::id()
            ?? $supplier?->account_id
            ?? $this->resolveOptionalAdminAccount($request)?->id;

        return $request->validate([
            'reference' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9_-]+$/',
                Rule::unique('suppliers', 'reference')
                    ->where(fn ($q) => $q->where('account_id', $accountId))
                    ->ignore($supplier?->id),
            ],
            'name' => 'required|string|max:255',
            'status' => 'nullable|in:active,inactive',
            'sources' => 'nullable|array',
            'sources.*.sid' => 'required_with:sources|string|max:255|regex:/^[a-z0-9_-]+$/i',
            'sources.*.name' => 'nullable|string|max:255',
            'sources.*.payout_override' => 'nullable|numeric|min:0',
            'sources.*.sub_suppliers' => 'nullable|array',
            'sources.*.sub_suppliers.*.ssid' => 'required_with:sources.*.sub_suppliers|string|max:255|regex:/^[a-z0-9_-]+$/i',
            'sources.*.sub_suppliers.*.name' => 'nullable|string|max:255',
            'rev_share_percent' => 'nullable|numeric|min:0|max:100',
            'default_postback_url' => 'nullable|url|max:500',
            'enable_sub_suppliers' => 'boolean',
            'portal_email' => 'nullable|email|max:255',
            'portal_password' => 'nullable|string|min:8|max:255',
            'portal_name' => 'nullable|string|max:255',
        ], [
            'reference.required' => 'Supplier reference is required.',
            'reference.regex' => 'Reference may only contain letters, numbers, hyphens and underscores.',
            'reference.unique' => 'This supplier reference already exists on your platform.',
            'name.required' => 'Supplier name is required.',
            'sources.*.sid.regex' => 'SID may only contain letters, numbers, hyphens and underscores.',
            'portal_password.min' => 'Portal password must be at least 8 characters.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{email: ?string, password: ?string, name: ?string}
     */
    protected function extractPortalFields(array &$validated): array
    {
        $portal = [
            'email' => $validated['portal_email'] ?? null,
            'password' => $validated['portal_password'] ?? null,
            'name' => $validated['portal_name'] ?? null,
        ];

        unset($validated['portal_email'], $validated['portal_password'], $validated['portal_name']);

        return $portal;
    }

    /**
     * @param  list<array{sid: string, name?: string, payout_override?: float}>  $sources
     */
    protected function syncSources(Supplier $supplier, array $sources): void
    {
        $sources = array_values(array_filter($sources, fn ($s) => ! empty($s['sid'])));

        $keepIds = [];
        foreach ($sources as $sourceData) {
            $source = $supplier->sources()->updateOrCreate(
                ['sid' => strtolower($sourceData['sid'])],
                [
                    'name' => $sourceData['name'] ?? $sourceData['sid'],
                    'payout_override' => $sourceData['payout_override'] ?? null,
                ]
            );
            $keepIds[] = $source->id;

            $this->syncSubSuppliers($source, $sourceData['sub_suppliers'] ?? []);
        }

        if ($supplier->exists) {
            $supplier->sources()->whereNotIn('id', $keepIds)->delete();
        }
    }

    /**
     * @param  list<array{ssid: string, name?: string}>  $subSuppliers
     */
    protected function syncSubSuppliers(Source $source, array $subSuppliers): void
    {
        $subSuppliers = array_values(array_filter($subSuppliers, fn ($s) => ! empty($s['ssid'])));
        $keepIds = [];

        foreach ($subSuppliers as $subData) {
            $sub = $source->subSuppliers()->updateOrCreate(
                ['ssid' => strtolower($subData['ssid'])],
                ['name' => $subData['name'] ?? $subData['ssid']]
            );
            $keepIds[] = $sub->id;
        }

        $source->subSuppliers()->whereNotIn('id', $keepIds)->delete();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function affiliateSettingsFrom(array $validated): array
    {
        return array_filter([
            'rev_share_percent' => isset($validated['rev_share_percent']) && $validated['rev_share_percent'] !== ''
                ? (float) $validated['rev_share_percent']
                : null,
            'default_postback_url' => $validated['default_postback_url'] ?? null,
            'enable_sub_suppliers' => (bool) ($validated['enable_sub_suppliers'] ?? false),
        ], fn ($v) => $v !== null);
    }

    /**
     * @param  array{email: ?string, password: ?string, name: ?string}  $portal
     */
    protected function syncPortalUser(Supplier $supplier, array $portal): void
    {
        if (empty($portal['email'])) {
            return;
        }

        $user = User::query()
            ->where('supplier_id', $supplier->id)
            ->where('role', UserRole::SupplierPortal)
            ->first();

        $data = [
            'account_id' => $supplier->account_id,
            'supplier_id' => $supplier->id,
            'email' => $portal['email'],
            'name' => $portal['name'] ?: $supplier->name.' Portal',
            'role' => UserRole::SupplierPortal,
        ];

        if (! empty($portal['password'])) {
            $data['password'] = $portal['password'];
        }

        if ($user) {
            $user->update(array_filter($data, fn ($v) => $v !== null));
        } else {
            $data['password'] = $data['password'] ?? ($portal['password'] ?? 'password');
            User::create($data);
        }
    }
}
