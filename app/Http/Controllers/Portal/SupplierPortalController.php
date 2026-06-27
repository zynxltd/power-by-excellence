<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\HostedForm;
use App\Models\Lead;
use App\Models\Postback;
use App\Services\Forms\HostedFormEmbedService;
use App\Services\Forms\SupplierHostedFormService;
use App\Services\Portal\PortalIntegrationsService;
use App\Services\Postbacks\SupplierPostbackService;
use App\Services\Suppliers\SupplierPortalService;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SupplierPortalController extends Controller
{
    public function __construct(
        protected SupplierPortalService $portal,
        protected PortalIntegrationsService $integrations,
        protected SupplierHostedFormService $supplierForms,
        protected SupplierPostbackService $supplierPostbacks,
    ) {}

    public function dashboard(Request $request): Response
    {
        $supplier = $this->resolveSupplier($request);
        $account = $request->user()->account ?? $supplier->account;

        return Inertia::render('Portal/Supplier/Dashboard', [
            'supplier' => $supplier->only(['id', 'name', 'reference', 'status']),
            'stats' => $this->portal->dashboardStats($supplier),
            'account' => $this->portal->accountSummary($supplier),
            'recentLeads' => $this->portal->recentLeads($supplier->id, 10),
            'recentActivity' => $this->portal->recentActivity($supplier->id, 8),
            'sourcePerformance' => $this->portal->sourcePerformance($supplier->id),
            'charts' => $this->portal->charts($supplier->id),
            'currency' => $account?->default_currency ?? 'GBP',
        ]);
    }

    public function leads(Request $request): Response
    {
        $supplier = $this->resolveSupplier($request);
        $account = $request->user()->account ?? $supplier->account;

        $query = Lead::where('supplier_id', $supplier->id)
            ->with(['campaign', 'financials']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->integer('campaign_id'));
        }

        if ($request->filled('sid')) {
            $query->where('sid', $request->sid);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('uuid', 'like', "%{$search}%")
                    ->orWhere('field_data->email', 'like', "%{$search}%")
                    ->orWhere('field_data->firstname', 'like', "%{$search}%")
                    ->orWhere('field_data->lastname', 'like', "%{$search}%");
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('received_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('received_at', '<=', $request->to_date);
        }

        $leads = $this->portal->paginateLeads(
            $query->orderByDesc('received_at')->paginate(25)->withQueryString(),
        );

        $campaigns = Lead::where('supplier_id', $supplier->id)
            ->join('campaigns', 'campaigns.id', '=', 'leads.campaign_id')
            ->distinct()
            ->orderBy('campaigns.name')
            ->get(['campaigns.id', 'campaigns.name', 'campaigns.reference']);

        $sids = Lead::where('supplier_id', $supplier->id)
            ->whereNotNull('sid')
            ->where('sid', '!=', '')
            ->distinct()
            ->orderBy('sid')
            ->pluck('sid');

        return Inertia::render('Portal/Supplier/Leads', [
            'leads' => $leads,
            'filters' => $request->only(['status', 'campaign_id', 'sid', 'search', 'from_date', 'to_date']),
            'campaigns' => $campaigns,
            'sids' => $sids,
            'statuses' => ['pending', 'processing', 'sold', 'unsold', 'rejected', 'quarantined', 'duplicate'],
            'account' => $this->portal->accountSummary($supplier),
            'recentActivity' => $this->portal->recentActivity($supplier->id, 10),
            'currency' => $account?->default_currency ?? 'GBP',
        ]);
    }

    public function showLead(Request $request, string $uuid): Response
    {
        $supplier = $this->resolveSupplier($request);
        $account = $request->user()->account ?? $supplier->account;

        $lead = Lead::query()
            ->where('uuid', $uuid)
            ->where('supplier_id', $supplier->id)
            ->with(['campaign', 'financials', 'source'])
            ->firstOrFail();

        return Inertia::render('Portal/Supplier/Show', [
            'lead' => $this->portal->formatLeadDetail($lead),
            'currency' => $account?->default_currency ?? 'GBP',
        ]);
    }

    public function downloadLeads(Request $request)
    {
        $supplier = $this->resolveSupplier($request);

        $query = Lead::where('supplier_id', $supplier->id)
            ->with(['campaign', 'financials']);

        if ($request->filled('uuids')) {
            $query->whereIn('uuid', (array) $request->input('uuids'));
        } else {
            $this->applyLeadFilters($query, $request);
        }

        $leads = $query->orderByDesc('received_at')->limit(5000)->get();

        $csv = "uuid,campaign,status,sid,firstname,lastname,email,phone,payout,received_at,distributed_at\n";
        foreach ($leads as $lead) {
            $csv .= CsvExport::escapeRow([
                $lead->uuid,
                $lead->campaign?->reference ?? '',
                $lead->status->value,
                $lead->sid,
                $lead->getField('firstname'),
                $lead->getField('lastname'),
                $lead->getField('email'),
                $lead->getField('phone1'),
                $lead->financials?->payout ?? 0,
                $lead->received_at,
                $lead->distributed_at,
            ])."\n";
        }

        return CsvExport::download($csv, 'supplier-leads.csv');
    }

    public function embeds(Request $request): Response
    {
        $supplier = $this->resolveSupplier($request);

        $account = $request->user()->account ?? $supplier->account;
        $embedService = app(HostedFormEmbedService::class);
        $iframeEmbedAllowed = $embedService->accountAllowsSupplierIframeEmbed($account);

        $forms = collect($embedService->formsForSupplier($supplier))->map(function ($form) use ($embedService, $supplier) {
            $tracking = $embedService->formTrackingParams($form, $supplier);

            return [
                'id' => $form->id,
                'name' => $form->name,
                'slug' => $form->slug,
                'campaign' => $form->campaign?->only(['id', 'name', 'reference']),
                'default_sid' => $form->config['default_sid'] ?? $tracking['sid'] ?? null,
                'embed' => $embedService->embedPayload($form, $tracking),
            ];
        })->values();

        return Inertia::render('Portal/Supplier/Embeds', [
            'supplier' => $supplier->only(['id', 'name', 'reference']),
            'sources' => $supplier->sources()->orderBy('sid')->get(['id', 'sid', 'name']),
            'iframeEmbedAllowed' => $iframeEmbedAllowed,
            'forms' => $forms,
            'campaigns' => $this->supplierForms->campaignsForSupplier($supplier),
            'requests' => $this->supplierForms->requestsForSupplier($supplier),
            'trackingParams' => HostedFormEmbedService::TRACKING_QUERY_PARAMS,
        ]);
    }

    public function storeForm(Request $request): RedirectResponse
    {
        $supplier = $this->resolveSupplier($request);
        $validated = $this->validateSupplierForm($request);

        $form = $this->supplierForms->create($supplier, $validated);

        return back()->with('success', 'Form draft saved. Submit it for tenant approval when ready.');
    }

    public function updateForm(Request $request, HostedForm $hostedForm): RedirectResponse
    {
        $supplier = $this->resolveSupplier($request);
        $validated = $this->validateSupplierForm($request);

        $this->supplierForms->update($supplier, $hostedForm, $validated);

        return back()->with('success', 'Form draft updated.');
    }

    public function submitForm(Request $request, HostedForm $hostedForm): RedirectResponse
    {
        $supplier = $this->resolveSupplier($request);
        $validated = $request->validate([
            'submission_notes' => 'nullable|string|max:1000',
        ]);

        $this->supplierForms->submitForApproval(
            $supplier,
            $hostedForm,
            $request->user(),
            $validated['submission_notes'] ?? null,
        );

        return back()->with('success', 'Form submitted for tenant approval. You will see embed codes here once approved.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateSupplierForm(Request $request): array
    {
        return $request->validate([
            'campaign_id' => 'required|integer|exists:campaigns,id',
            'source_id' => 'nullable|integer|exists:sources,id',
            'name' => 'required|string|max:255',
            'redirect_url' => 'nullable|url|max:500',
            'allowed_domains' => 'nullable|array',
            'allowed_domains.*' => 'string|max:255',
        ]);
    }

    public function integrations(Request $request): Response
    {
        $supplier = $this->resolveSupplier($request);

        return Inertia::render('Portal/Supplier/Integrations', [
            ...$this->integrations->forSupplier(
                $supplier,
                $request->integer('campaign_id') ?: null,
            ),
        ]);
    }

    public function storePostback(Request $request): RedirectResponse
    {
        $supplier = $this->resolveSupplier($request);
        $validated = $this->validateSupplierPostback($request);

        $this->supplierPostbacks->create($supplier, $validated);

        return back()->with('success', 'Postback draft saved. Submit it for tenant approval when ready.');
    }

    public function updatePostback(Request $request, Postback $postback): RedirectResponse
    {
        $supplier = $this->resolveSupplier($request);
        $validated = $this->validateSupplierPostback($request);

        $this->supplierPostbacks->update($supplier, $postback, $validated);

        return back()->with('success', 'Postback draft updated.');
    }

    public function submitPostback(Request $request, Postback $postback): RedirectResponse
    {
        $supplier = $this->resolveSupplier($request);
        $validated = $request->validate([
            'submission_notes' => 'nullable|string|max:1000',
        ]);

        $this->supplierPostbacks->submitForApproval(
            $supplier,
            $postback,
            $request->user(),
            $validated['submission_notes'] ?? null,
        );

        return back()->with('success', 'Postback submitted for tenant approval.');
    }

    public function destroyPostback(Request $request, Postback $postback): RedirectResponse
    {
        $supplier = $this->resolveSupplier($request);
        $this->supplierPostbacks->deleteDraft($supplier, $postback);

        return back()->with('success', 'Postback draft deleted.');
    }

    public function requestPostbackDeletion(Request $request, Postback $postback): RedirectResponse
    {
        $supplier = $this->resolveSupplier($request);
        $validated = $request->validate([
            'submission_notes' => 'nullable|string|max:1000',
        ]);

        $this->supplierPostbacks->requestDeletion(
            $supplier,
            $postback,
            $request->user(),
            $validated['submission_notes'] ?? null,
        );

        return back()->with('success', 'Deletion request sent to your platform administrator.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateSupplierPostback(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2000',
            'method' => 'required|in:get,post',
            'events' => 'required|array|min:1',
            'events.*' => 'string|in:'.implode(',', SupplierPostbackService::eventOptions()),
            'campaign_id' => 'nullable|integer|exists:campaigns,id',
        ]);
    }

    public function importLeads(Request $request): Response
    {
        $supplier = $this->resolveSupplier($request);

        return Inertia::render('Portal/Supplier/ImportLeads', [
            'supplier' => $supplier->only(['id', 'name', 'reference']),
            'campaigns' => $this->supplierForms->campaignsForSupplier($supplier),
        ]);
    }

    public function storeImport(Request $request): RedirectResponse
    {
        $supplier = $this->resolveSupplier($request);

        $validated = $request->validate([
            'campaign_id' => 'required|integer|exists:campaigns,id',
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $campaign = Campaign::findOrFail($validated['campaign_id']);
        abort_unless(
            $campaign->campaignSuppliers()->where('supplier_id', $supplier->id)->exists(),
            403,
        );

        $import = app(\App\Services\Leads\CsvImportService::class)->import(
            $request->file('file'),
            $campaign,
            $request->user()->id,
        );

        return redirect()
            ->route('portal.supplier.leads')
            ->with('success', "Import complete: {$import->success_rows} succeeded, {$import->failed_rows} failed.");
    }

    public function downloadPayouts(Request $request, \App\Services\Exports\SupplierPayoutExportService $export)
    {
        $supplier = $this->resolveSupplier($request);
        $csv = $export->buildCsv($supplier, $request);

        return CsvExport::download($csv, 'payouts.csv');
    }

    public function billing(Request $request): Response
    {
        $supplier = $this->resolveSupplier($request);
        $account = $request->user()->account ?? $supplier->account;

        $totalPayout = (float) DB::table('lead_financials')
            ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
            ->where('leads.supplier_id', $supplier->id)
            ->sum('lead_financials.payout');

        $payoutThisMonth = (float) DB::table('lead_financials')
            ->join('leads', 'leads.id', '=', 'lead_financials.lead_id')
            ->where('leads.supplier_id', $supplier->id)
            ->whereMonth('leads.distributed_at', now()->month)
            ->whereYear('leads.distributed_at', now()->year)
            ->sum('lead_financials.payout');

        $payoutsQuery = Lead::where('supplier_id', $supplier->id)
            ->where('status', 'sold')
            ->with(['financials', 'campaign']);

        if ($request->filled('from_date')) {
            $payoutsQuery->whereDate('distributed_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $payoutsQuery->whereDate('distributed_at', '<=', $request->to_date);
        }

        if ($request->filled('campaign_id')) {
            $payoutsQuery->where('campaign_id', $request->integer('campaign_id'));
        }

        if ($request->filled('sid')) {
            $payoutsQuery->where('sid', $request->sid);
        }

        $payouts = $payoutsQuery
            ->orderByDesc('distributed_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Lead $lead) => [
                'uuid' => $lead->uuid,
                'campaign' => $lead->campaign?->reference,
                'sid' => $lead->sid,
                'payout' => $lead->financials?->payout ?? 0,
                'distributed_at' => $lead->distributed_at?->toDateTimeString(),
            ]);

        $campaigns = Lead::where('supplier_id', $supplier->id)
            ->where('leads.status', 'sold')
            ->join('campaigns', 'campaigns.id', '=', 'leads.campaign_id')
            ->distinct()
            ->orderBy('campaigns.name')
            ->get(['campaigns.id', 'campaigns.name', 'campaigns.reference']);

        $sids = Lead::where('supplier_id', $supplier->id)
            ->where('status', 'sold')
            ->whereNotNull('sid')
            ->where('sid', '!=', '')
            ->distinct()
            ->orderBy('sid')
            ->pluck('sid');

        return Inertia::render('Portal/Supplier/Billing', [
            'supplier' => $supplier->only(['id', 'name', 'reference']),
            'account' => $this->portal->accountSummary($supplier),
            'stats' => $this->portal->dashboardStats($supplier),
            'currency' => $account?->default_currency ?? 'GBP',
            'summary' => [
                'total_payout' => $totalPayout,
                'payout_this_month' => $payoutThisMonth,
                'sold_count' => Lead::where('supplier_id', $supplier->id)->where('status', 'sold')->count(),
            ],
            'payouts' => $payouts,
            'filters' => $request->only(['from_date', 'to_date', 'campaign_id', 'sid']),
            'campaigns' => $campaigns,
            'sids' => $sids,
        ]);
    }

    public function clicks(Request $request): Response
    {
        $supplier = $this->resolveSupplier($request);
        $account = $request->user()->account ?? $supplier->account;
        $clickStats = $this->portal->clickStats($supplier);

        return Inertia::render('Portal/Supplier/Clicks', [
            'supplier' => $supplier->only(['id', 'name', 'reference']),
            'account' => $this->portal->accountSummary($supplier),
            'stats' => $clickStats,
            'currency' => $account?->default_currency ?? 'GBP',
        ]);
    }

    protected function resolveSupplier(Request $request): \App\Models\Supplier
    {
        $supplier = $request->user()->supplier;
        abort_unless($supplier, 403, 'Supplier account not linked to this user.');

        return $supplier;
    }

    protected function applyLeadFilters($query, Request $request): void
    {
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->integer('campaign_id'));
        }

        if ($request->filled('sid')) {
            $query->where('sid', $request->sid);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('uuid', 'like', "%{$search}%")
                    ->orWhere('field_data->email', 'like', "%{$search}%")
                    ->orWhere('field_data->firstname', 'like', "%{$search}%")
                    ->orWhere('field_data->lastname', 'like', "%{$search}%");
            });
        }

        if ($request->filled('from_date')) {
            $query->whereDate('received_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('received_at', '<=', $request->to_date);
        }
    }
}
