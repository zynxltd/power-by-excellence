<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Support\Admin\ResolvesAdminAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LeadSourceIntegrationController extends Controller
{
    use ResolvesAdminAccount;

    public function edit(Request $request, string $provider): Response
    {
        $account = $this->resolveAdminAccount($request);
        $providers = $this->providers();
        abort_unless(isset($providers[$provider]), 404);

        $meta = $providers[$provider];
        $config = $account->settings['lead_sources'][$provider] ?? [
            'enabled' => false,
            'verify_token' => '',
            'campaign_id' => null,
            'field_mapping' => [],
        ];

        return Inertia::render('Admin/Integrations/LeadSource', [
            'provider' => $provider,
            'meta' => $meta,
            'config' => $config,
            'campaigns' => Campaign::orderBy('name')->get(['id', 'name', 'reference']),
            'webhookUrl' => url("/api/v1/integrations/{$provider}/webhook/{$account->slug}"),
            'ingestUrl' => url("/api/v1/integrations/{$provider}/ingest/{$account->slug}"),
        ]);
    }

    public function update(Request $request, string $provider): RedirectResponse
    {
        $account = $this->resolveAdminAccount($request);
        abort_unless(isset($this->providers()[$provider]), 404);

        $validated = $request->validate([
            'enabled' => 'boolean',
            'verify_token' => 'nullable|string|max:255',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'field_mapping' => 'nullable|array',
        ]);

        if (empty($validated['verify_token'])) {
            $validated['verify_token'] = Str::random(32);
        }

        $settings = $account->settings ?? [];
        $settings['lead_sources'] = $settings['lead_sources'] ?? [];
        $settings['lead_sources'][$provider] = array_merge(
            $settings['lead_sources'][$provider] ?? [],
            $validated
        );

        $account->update(['settings' => $settings]);

        return back()->with('success', $this->providers()[$provider]['name'].' settings saved.');
    }

    /**
     * @return array<string, array{name: string, description: string}>
     */
    protected function providers(): array
    {
        return [
            'facebook' => [
                'name' => 'Facebook Lead Ads',
                'description' => 'Receive leads from Facebook Lead Ad forms via webhook subscription.',
            ],
            'google' => [
                'name' => 'Google Ads Lead Forms',
                'description' => 'Import leads from Google Ads lead form extensions using webhook push.',
            ],
            'tiktok' => [
                'name' => 'TikTok Lead Gen',
                'description' => 'Sync TikTok instant form leads into your campaigns in real time.',
            ],
        ];
    }
}
