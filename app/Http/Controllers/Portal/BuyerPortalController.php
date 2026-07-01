<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\BuyerInvoice;
use App\Models\BuyerFeedback;
use App\Models\Lead;
use App\Models\LeadReturn;
use App\Models\Webhook;
use App\Services\Billing\BuyerInvoiceService;
use App\Services\Buyers\BuyerPortalService;
use App\Services\Platform\PlatformNotificationService;
use App\Services\Portal\PortalIntegrationsService;
use App\Services\Webhooks\BuyerWebhookService;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BuyerPortalController extends Controller
{
    public function __construct(
        protected BuyerPortalService $portal,
        protected PortalIntegrationsService $integrations,
        protected BuyerWebhookService $buyerWebhooks,
    ) {}

    public function dashboard(Request $request): Response
    {
        $buyer = $this->resolveBuyer($request);

        $recentLeads = Lead::where('sold_to_buyer_id', $buyer->id)
            ->with(['financials', 'campaign'])
            ->orderByDesc('distributed_at')
            ->limit(10)
            ->get()
            ->map(fn (Lead $lead) => $this->portal->formatLeadRow($lead));

        return Inertia::render('Portal/Buyer/Dashboard', [
            'buyer' => $buyer->only(['id', 'name', 'credit_balance', 'status']),
            'stats' => $this->portal->dashboardStats($buyer),
            'account' => $this->portal->accountSummary($buyer),
            'recentLeads' => $recentLeads,
            'recentActivity' => $this->portal->recentActivity($buyer->id, 8),
            'charts' => $this->portal->charts($buyer->id),
            'currency' => $buyer->resolvedCurrency(),
        ]);
    }

    public function leads(Request $request): Response
    {
        $buyer = $this->resolveBuyer($request);

        $query = Lead::where('sold_to_buyer_id', $buyer->id)
            ->with(['campaign', 'financials', 'supplier:id,name,reference']);

        $this->applyLeadFilters($query, $request, $buyer);

        $leads = $this->portal->paginateLeads(
            $query->orderByDesc('distributed_at')->paginate(25)->withQueryString(),
            $buyer->id,
        );

        $campaigns = Lead::where('sold_to_buyer_id', $buyer->id)
            ->join('campaigns', 'campaigns.id', '=', 'leads.campaign_id')
            ->distinct()
            ->orderBy('campaigns.name')
            ->get(['campaigns.id', 'campaigns.name', 'campaigns.reference']);

        $suppliers = Lead::where('sold_to_buyer_id', $buyer->id)
            ->join('suppliers', 'suppliers.id', '=', 'leads.supplier_id')
            ->distinct()
            ->orderBy('suppliers.name')
            ->get(['suppliers.id', 'suppliers.name', 'suppliers.reference']);

        $sids = Lead::where('sold_to_buyer_id', $buyer->id)
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', $request->integer('supplier_id')))
            ->whereNotNull('sid')
            ->where('sid', '!=', '')
            ->distinct()
            ->orderBy('sid')
            ->pluck('sid');

        return Inertia::render('Portal/Buyer/Leads', [
            'leads' => $leads,
            'filters' => $request->only(['status', 'campaign_id', 'supplier_id', 'sid', 'search', 'from_date', 'to_date', 'feedback', 'return']),
            'campaigns' => $campaigns,
            'suppliers' => $suppliers,
            'sids' => $sids,
            'statuses' => ['sold', 'pending', 'processing', 'unsold', 'rejected'],
            'account' => $this->portal->accountSummary($buyer),
            'recentActivity' => $this->portal->recentActivity($buyer->id, 10),
            'actionLeads' => $this->portal->actionLeadOptions($buyer->id),
            'currency' => $buyer->resolvedCurrency(),
        ]);
    }

    public function showLead(Request $request, string $uuid): Response
    {
        $buyer = $this->resolveBuyer($request);

        $lead = Lead::query()
            ->where('uuid', $uuid)
            ->where('sold_to_buyer_id', $buyer->id)
            ->with(['campaign', 'financials'])
            ->firstOrFail();

        return Inertia::render('Portal/Buyer/Show', [
            'lead' => $this->portal->formatLeadDetail($lead, $buyer),
            'currency' => $buyer->resolvedCurrency(),
        ]);
    }

    public function downloadLeads(Request $request)
    {
        $buyer = $this->resolveBuyer($request);

        $query = Lead::where('sold_to_buyer_id', $buyer->id)
            ->with('supplier:id,name,reference');

        if ($request->filled('uuids')) {
            $query->whereIn('uuid', (array) $request->input('uuids'));
        } else {
            $this->applyLeadFilters($query, $request, $buyer);
        }

        $leads = $query->orderByDesc('distributed_at')->limit(5000)->get();

        $csv = "uuid,supplier,sid,firstname,lastname,email,phone1,zipcode,status,revenue,received_at,distributed_at\n";
        foreach ($leads as $lead) {
            $csv .= CsvExport::escapeRow([
                $lead->uuid,
                $lead->supplier?->reference ?? $lead->supplier?->name,
                $lead->sid,
                $lead->getField('firstname'),
                $lead->getField('lastname'),
                $lead->getField('email'),
                $lead->getField('phone1'),
                $lead->getField('zipcode'),
                $lead->status->value,
                $lead->financials?->revenue ?? 0,
                $lead->received_at,
                $lead->distributed_at,
            ])."\n";
        }

        return CsvExport::download($csv, 'leads.csv');
    }

    public function feedback(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lead_uuid' => 'required|string',
            'status' => 'required|string|in:contacted,converted,invalid,funded,called,callback',
            'converted' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        $buyer = $this->resolveBuyer($request);
        $lead = $this->resolveLeadForBuyer($buyer, $validated['lead_uuid']);

        $result = app(\App\Services\Buyers\BuyerConversionService::class)->recordFeedback(
            $buyer,
            $lead,
            $validated['status'],
            $validated['converted'] ?? false,
            $validated['notes'] ?? null,
        );

        app(PlatformNotificationService::class)->notifyTenantBuyerFeedback(
            $buyer->account,
            $request->user(),
            $buyer,
            1,
            $validated['status'],
            $validated['converted'] ?? false,
            $validated['notes'] ?? null,
            $result['feedback_id'] ?? null,
            $result['lead_id'] ?? null,
        );

        return back()->with('success', 'Feedback recorded. Your account manager can see this on the lead timeline.');
    }

    public function returnLead(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lead_uuid' => 'required|string',
            'reason' => 'required|string|max:500',
        ]);

        $buyer = $this->resolveBuyer($request);
        $lead = $this->resolveLeadForBuyer($buyer, $validated['lead_uuid']);

        if (LeadReturn::query()
            ->where('lead_id', $lead->id)
            ->where('buyer_id', $buyer->id)
            ->where('status', 'pending')
            ->exists()) {
            return back()->withErrors([
                'lead_uuid' => 'A return is already pending review for this lead.',
            ]);
        }

        LeadReturn::create([
            'lead_id' => $lead->id,
            'buyer_id' => $buyer->id,
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        return back()->with('success', 'Return submitted. Your platform administrator will review and approve or reject it.');
    }

    public function bulkFeedback(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lead_uuids' => 'required|array|min:1|max:100',
            'lead_uuids.*' => 'required|string|distinct',
            'status' => 'required|string|in:contacted,converted,invalid,funded,called,callback',
            'converted' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        $buyer = $this->resolveBuyer($request);
        $service = app(\App\Services\Buyers\BuyerConversionService::class);
        $processed = 0;

        foreach ($validated['lead_uuids'] as $uuid) {
            $lead = $this->resolveLeadForBuyer($buyer, $uuid);
            $service->recordFeedback(
                $buyer,
                $lead,
                $validated['status'],
                $validated['converted'] ?? false,
                $validated['notes'] ?? null,
            );
            $processed++;
        }

        if ($processed > 0) {
            app(PlatformNotificationService::class)->notifyTenantBuyerFeedback(
                $buyer->account,
                $request->user(),
                $buyer,
                $processed,
                $validated['status'],
                $validated['converted'] ?? false,
                $validated['notes'] ?? null,
            );
        }

        return back()->with('success', "Feedback recorded for {$processed} lead(s).");
    }

    public function bulkReturn(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'lead_uuids' => 'required|array|min:1|max:100',
            'lead_uuids.*' => 'required|string|distinct',
            'reason' => 'required|string|max:500',
        ]);

        $buyer = $this->resolveBuyer($request);
        $processed = 0;
        $skipped = 0;

        foreach ($validated['lead_uuids'] as $uuid) {
            $lead = $this->resolveLeadForBuyer($buyer, $uuid);

            if (LeadReturn::query()
                ->where('lead_id', $lead->id)
                ->where('buyer_id', $buyer->id)
                ->where('status', 'pending')
                ->exists()) {
                $skipped++;

                continue;
            }

            LeadReturn::create([
                'lead_id' => $lead->id,
                'buyer_id' => $buyer->id,
                'reason' => $validated['reason'],
                'status' => 'pending',
            ]);
            $processed++;
        }

        $message = "Return submitted for {$processed} lead(s).";
        if ($skipped > 0) {
            $message .= " {$skipped} skipped (already pending).";
        }

        return back()->with('success', $message);
    }

    public function transactions(Request $request): Response
    {
        return $this->billing($request);
    }

    public function billing(Request $request): Response
    {
        $buyer = $this->resolveBuyer($request);
        $account = $buyer->account;

        $stripe = app(\App\Services\Billing\StripeCheckoutService::class);

        return Inertia::render('Portal/Buyer/Billing', [
            'buyer' => $buyer->only(['id', 'name', 'credit_balance', 'status']),
            'account' => $this->portal->accountSummary($buyer),
            'stats' => $this->portal->dashboardStats($buyer),
            'requirePrepay' => (bool) ($account?->settings['require_buyer_prepay'] ?? false),
            'stripeEnabled' => $stripe->buyerSelfServeEnabled($account),
            'stripeTopUp' => [
                'min' => $stripe->minimumTopUp($account),
                'presets' => $stripe->presetAmounts($account),
            ],
            'stripeSubscriptionsEnabled' => $stripe->subscriptionsEnabled($account),
            'stripeSubscriptionPlans' => $stripe->subscriptionPlans($account),
            'stripeSubscription' => $stripe->subscriptionStatus($buyer),
            'currency' => $buyer->resolvedCurrency(),
            'transactions' => $buyer->transactions()->orderByDesc('created_at')->paginate(25),
            'invoices' => $buyer->invoices()->orderByDesc('created_at')->paginate(25),
            'invoiceResendEnabled' => Route::has('portal.buyer.invoices.resend'),
        ]);
    }

    public function resendInvoice(Request $request, BuyerInvoice $invoice): RedirectResponse
    {
        $buyer = $this->resolveBuyer($request);

        if ($invoice->buyer_id !== $buyer->id) {
            abort(404);
        }

        app(BuyerInvoiceService::class)->resendInvoiceEmail($invoice);

        return back()->with('success', 'Invoice email queued for delivery.');
    }

    public function integrations(Request $request): Response
    {
        $buyer = $this->resolveBuyer($request);

        return Inertia::render('Portal/Buyer/Integrations', [
            ...$this->integrations->forBuyer($buyer),
        ]);
    }

    public function storeWebhook(Request $request): RedirectResponse
    {
        $buyer = $this->resolveBuyer($request);
        $validated = $this->validateBuyerWebhook($request);

        $this->buyerWebhooks->create($buyer, $validated);

        return back()->with('success', 'Webhook draft saved. Submit it for tenant approval when ready.');
    }

    public function updateWebhook(Request $request, Webhook $webhook): RedirectResponse
    {
        $buyer = $this->resolveBuyer($request);
        $validated = $this->validateBuyerWebhook($request);

        $this->buyerWebhooks->update($buyer, $webhook, $validated);

        return back()->with('success', 'Webhook draft updated.');
    }

    public function submitWebhook(Request $request, Webhook $webhook): RedirectResponse
    {
        $buyer = $this->resolveBuyer($request);
        $validated = $request->validate([
            'submission_notes' => 'nullable|string|max:1000',
        ]);

        $this->buyerWebhooks->submitForApproval(
            $buyer,
            $webhook,
            $request->user(),
            $validated['submission_notes'] ?? null,
        );

        return back()->with('success', 'Webhook submitted for tenant approval.');
    }

    public function destroyWebhook(Request $request, Webhook $webhook): RedirectResponse
    {
        $buyer = $this->resolveBuyer($request);
        $this->buyerWebhooks->deleteDraft($buyer, $webhook);

        return back()->with('success', 'Webhook draft deleted.');
    }

    public function requestWebhookDeletion(Request $request, Webhook $webhook): RedirectResponse
    {
        $buyer = $this->resolveBuyer($request);
        $validated = $request->validate([
            'submission_notes' => 'nullable|string|max:1000',
        ]);

        $this->buyerWebhooks->requestDeletion(
            $buyer,
            $webhook,
            $request->user(),
            $validated['submission_notes'] ?? null,
        );

        return back()->with('success', 'Deletion request sent to your platform administrator.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validateBuyerWebhook(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'url' => 'required|url|max:2000',
            'events' => 'required|array|min:1',
            'events.*' => 'string|in:'.implode(',', BuyerWebhookService::eventOptions()),
        ]);
    }

    protected function resolveBuyer(Request $request): \App\Models\Buyer
    {
        $buyer = $request->user()->buyer;
        abort_unless($buyer, 403, 'Buyer account not linked to this user.');

        return $buyer;
    }

    protected function resolveLeadForBuyer(\App\Models\Buyer $buyer, string $uuid): Lead
    {
        $lead = Lead::query()
            ->where('uuid', $uuid)
            ->where('sold_to_buyer_id', $buyer->id)
            ->first();

        if (! $lead) {
            throw ValidationException::withMessages([
                'lead_uuid' => 'Lead not found in your inventory. Pick a lead from the dropdown or use Feedback / Return on a row in the table.',
            ]);
        }

        return $lead;
    }

    protected function applyLeadFilters($query, Request $request, \App\Models\Buyer $buyer): void
    {
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('campaign_id')) {
            $query->where('campaign_id', $request->integer('campaign_id'));
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->integer('supplier_id'));
        }

        if ($request->filled('sid')) {
            $query->where('sid', $request->string('sid')->toString());
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
            $query->whereDate('distributed_at', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('distributed_at', '<=', $request->to_date);
        }

        if ($request->filled('feedback')) {
            $feedback = $request->string('feedback')->toString();
            $reportedIds = BuyerFeedback::query()->where('buyer_id', $buyer->id);

            if ($feedback === 'none') {
                $query->whereNotIn('id', (clone $reportedIds)->pluck('lead_id'));
            } elseif ($feedback === 'converted') {
                $query->whereIn('id', (clone $reportedIds)->where('converted', true)->pluck('lead_id'));
            } elseif ($feedback === 'reported') {
                $query->whereIn('id', (clone $reportedIds)->pluck('lead_id'));
            }
        }

        if ($request->filled('return')) {
            $returnStatus = $request->string('return')->toString();
            $leadIds = LeadReturn::query()
                ->where('buyer_id', $buyer->id)
                ->where('status', $returnStatus)
                ->pluck('lead_id');

            $query->whereIn('id', $leadIds);
        }
    }
}
