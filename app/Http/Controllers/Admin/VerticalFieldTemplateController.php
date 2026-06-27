<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignField;
use App\Models\VerticalFieldTemplate;
use App\Support\VerticalCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VerticalFieldTemplateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/VerticalFieldTemplates/Index', [
            'templates' => VerticalFieldTemplate::orderBy('vertical_id')->orderBy('name')->paginate(25),
            'verticals' => VerticalCatalog::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
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

    public function apply(Request $request, VerticalFieldTemplate $verticalFieldTemplate): RedirectResponse
    {
        $validated = $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
        ]);

        $campaign = Campaign::findOrFail($validated['campaign_id']);

        CampaignField::where('campaign_id', $campaign->id)->delete();

        foreach ($verticalFieldTemplate->fields as $i => $field) {
            CampaignField::create(array_merge($field, [
                'campaign_id' => $campaign->id,
                'sort_order' => $i,
            ]));
        }

        return redirect()
            ->route('campaigns.show', $campaign)
            ->with('success', "Applied template \"{$verticalFieldTemplate->name}\" to {$campaign->name}.");
    }
}
