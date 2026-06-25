<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Lead;
use App\Services\Distribution\RoutingSimulatorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoutingSimulatorController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Routing/Simulator', [
            'campaigns' => Campaign::with('fields')->orderBy('name')->get(['id', 'name', 'reference', 'use_advanced_distribution']),
            'recentLeads' => Lead::with('campaign:id,name')
                ->orderByDesc('received_at')
                ->limit(20)
                ->get(['id', 'uuid', 'campaign_id', 'field_data', 'status']),
            'filters' => [
                'campaign_id' => $request->input('campaign_id'),
            ],
        ]);
    }

    public function run(Request $request, RoutingSimulatorService $simulator): Response
    {
        $validated = $request->validate([
            'campaign_id' => 'required|exists:campaigns,id',
            'lead_id' => 'nullable|exists:leads,id',
            'field_data' => 'nullable|array',
        ]);

        $campaign = Campaign::findOrFail($validated['campaign_id']);
        $lead = isset($validated['lead_id']) ? Lead::find($validated['lead_id']) : null;
        $fieldData = $lead?->field_data ?? $validated['field_data'] ?? [
            'firstname' => 'Test',
            'lastname' => 'Lead',
            'email' => 'simulator@demo.test',
            'phone1' => '07700900123',
            'zipcode' => 'SW1A 1AA',
        ];

        $result = $simulator->simulate($campaign, $fieldData, $lead);

        return Inertia::render('Admin/Routing/Simulator', [
            'campaigns' => Campaign::with('fields')->orderBy('name')->get(['id', 'name', 'reference', 'use_advanced_distribution']),
            'recentLeads' => Lead::with('campaign:id,name')
                ->orderByDesc('received_at')
                ->limit(20)
                ->get(['id', 'uuid', 'campaign_id', 'field_data', 'status']),
            'filters' => ['campaign_id' => $campaign->id],
            'simulation' => $result,
            'simulationInput' => [
                'campaign_id' => $campaign->id,
                'lead_id' => $lead?->id,
                'field_data' => $fieldData,
            ],
        ]);
    }
}
