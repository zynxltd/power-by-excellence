<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\VerticalFieldTemplate;
use App\Services\Logging\PlatformLogger;
use App\Services\VerticalFieldTemplates\VerticalFieldTemplateApplyService;
use App\Support\Admin\CampaignWorkflow;
use App\Support\Admin\ResolvesAdminAccount;
use App\Support\VerticalCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VerticalFieldTemplateController extends Controller
{
    use ResolvesAdminAccount;

    public function index(Request $request): Response
    {
        $this->resolveAdminAccount($request);

        return Inertia::render('Admin/VerticalFieldTemplates/Index', [
            'templates' => VerticalFieldTemplate::orderBy('vertical_id')->orderBy('name')->paginate(25),
            'verticals' => VerticalCatalog::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->resolveAdminAccount($request);

        $validated = $request->validate([
            'vertical_id' => 'required|string|max:64',
            'name' => 'required|string|max:255',
            'fields' => 'required|array|min:1',
            'fields.*.name' => 'required|string|max:64',
            'fields.*.label' => 'nullable|string|max:255',
            'fields.*.type' => 'nullable|string|max:32',
            'fields.*.required' => 'boolean',
        ]);

        VerticalFieldTemplate::create($validated);

        return back()->with('success', 'Field template created.');
    }

    public function applyWizard(Request $request): Response
    {
        $this->resolveAdminAccount($request);

        $campaign = null;
        if ($request->filled('campaign_id')) {
            $campaign = Campaign::with('fields')->findOrFail($request->integer('campaign_id'));
            $this->resolveAdminAccountForTenant($request, $campaign->account_id);
        }

        $templatesQuery = VerticalFieldTemplate::query()->orderBy('name');
        if ($campaign) {
            $templatesQuery->where('vertical_id', $campaign->vertical_id);
        }

        return Inertia::render('Admin/VerticalFieldTemplates/ApplyWizard', [
            'campaign' => $campaign ? [
                'id' => $campaign->id,
                'name' => $campaign->name,
                'reference' => $campaign->reference,
                'vertical_id' => $campaign->vertical_id,
                'fields' => $campaign->fields,
            ] : null,
            'templates' => $templatesQuery->get(['id', 'name', 'vertical_id', 'fields']),
            'verticals' => VerticalCatalog::options(),
            'preselectedTemplateId' => $request->integer('template_id') ?: null,
            'campaignWorkflow' => $campaign ? CampaignWorkflow::forCampaign($campaign) : null,
            'defaultStrategy' => VerticalFieldTemplateApplyService::STRATEGY_REPLACE_ALL,
        ]);
    }

    public function preview(
        Request $request,
        VerticalFieldTemplate $verticalFieldTemplate,
        VerticalFieldTemplateApplyService $applyService,
    ): JsonResponse {
        $validated = $request->validate([
            'campaign_id' => 'required|integer|exists:campaigns,id',
            'strategy' => 'nullable|in:replace-all,merge-by-name',
        ]);

        $campaign = Campaign::with('fields')->findOrFail($validated['campaign_id']);
        $this->resolveAdminAccountForTenant($request, $campaign->account_id);

        try {
            $diff = $applyService->buildDiff(
                $campaign,
                $verticalFieldTemplate,
                $validated['strategy'] ?? VerticalFieldTemplateApplyService::STRATEGY_REPLACE_ALL,
            );
        } catch (ValidationException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => $exception->errors(),
            ], $exception->status);
        }

        return response()->json($diff);
    }

    public function apply(
        Request $request,
        VerticalFieldTemplate $verticalFieldTemplate,
        VerticalFieldTemplateApplyService $applyService,
    ): RedirectResponse {
        $validated = $request->validate([
            'campaign_id' => 'required|integer|exists:campaigns,id',
            'strategy' => 'nullable|in:replace-all,merge-by-name',
        ]);

        $campaign = Campaign::with('fields')->findOrFail($validated['campaign_id']);
        $this->resolveAdminAccountForTenant($request, $campaign->account_id);

        $strategy = $validated['strategy'] ?? VerticalFieldTemplateApplyService::STRATEGY_REPLACE_ALL;

        try {
            $applyService->apply($campaign, $verticalFieldTemplate, $strategy);
        } catch (ValidationException $exception) {
            return back()
                ->withErrors($exception->errors())
                ->withInput();
        }

        PlatformLogger::info('Vertical field template applied to campaign', [
            'campaign_id' => $campaign->id,
            'campaign_reference' => $campaign->reference,
            'template_id' => $verticalFieldTemplate->id,
            'template_name' => $verticalFieldTemplate->name,
            'strategy' => $strategy,
            'user_id' => $request->user()?->id,
        ]);

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('success', "Applied template \"{$verticalFieldTemplate->name}\" to {$campaign->name}.");
    }
}
