<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Support\Admin\ResolvesAdminAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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
            'page_access_token' => '',
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
            'page_access_token' => 'nullable|string|max:500',
            'campaign_id' => [
                'nullable',
                Rule::exists('campaigns', 'id')->where('account_id', $account->id),
            ],
            'field_mapping' => 'nullable|array',
        ]);

        if (empty($validated['verify_token']) && ($this->providers()[$provider]['requires_verify_token'] ?? false)) {
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
     * @return array<string, array<string, mixed>>
     */
    protected function providers(): array
    {
        return [
            'facebook' => [
                'name' => 'Facebook Lead Ads',
                'description' => 'Receive leads from Meta Lead Ad forms via webhook subscription.',
                'connection_type' => 'webhook',
                'oauth' => false,
                'configured_message' => 'Integration enabled. Add the verify token and webhook URL in your Meta App, then paste a Page access token so lead fields are fetched automatically.',
                'setup_steps' => [
                    'Create or open a Meta App at developers.facebook.com.',
                    'Subscribe to Page → leadgen webhooks and paste the verify token below.',
                    'Paste the webhook URL from this page into the Meta App webhook settings.',
                    'Generate a Page access token with leads_retrieval permission and paste it below.',
                ],
                'show_verify_token' => true,
                'verify_token_label' => 'Verify token',
                'verify_token_help' => 'Paste into Meta App → Webhooks → Page → leadgen → Verify token. Auto-generated when left blank on save.',
                'show_page_access_token' => true,
                'page_access_token_label' => 'Page access token',
                'page_access_token_help' => 'Required to fetch lead name, email, and phone from Meta when webhooks fire. Generate in Meta Business Suite or the Graph API explorer.',
                'webhook_help' => 'Callback URL for Meta App → Webhooks → Page → leadgen.',
                'ingest_help' => 'POST JSON with campaign field names (email, firstname, phone1, etc.) for testing, Zapier, or Make.',
                'requires_verify_token' => true,
            ],
            'google' => [
                'name' => 'Google Ads Lead Forms',
                'description' => 'Import leads from Google Ads lead form extensions via webhook push or automation tools.',
                'connection_type' => 'webhook',
                'oauth' => false,
                'configured_message' => 'Integration enabled. Point Google Ads lead-form webhooks or an automation tool (Zapier, Make) at the URLs on the right.',
                'setup_steps' => [
                    'In Google Ads, open your lead form asset and configure webhook delivery (or use Zapier/Make as a bridge).',
                    'Paste the webhook URL or direct ingest URL into Google Ads or your automation tool.',
                    'Map Google lead fields to your campaign fields (email, firstname, phone1, zipcode, etc.).',
                    'Send a test lead and confirm it appears under Leads for the target campaign.',
                ],
                'show_verify_token' => false,
                'verify_token_label' => null,
                'verify_token_help' => null,
                'show_page_access_token' => false,
                'page_access_token_label' => null,
                'page_access_token_help' => null,
                'webhook_help' => 'Receive POST payloads from Google Ads webhook delivery or middleware that forwards lead-form submissions.',
                'ingest_help' => 'POST JSON with campaign field names. Easiest path for Zapier, Make, or custom scripts - no Meta-style verify handshake.',
                'requires_verify_token' => false,
            ],
            'tiktok' => [
                'name' => 'TikTok Lead Gen',
                'description' => 'Sync TikTok instant form leads into your campaigns via webhook or direct ingest.',
                'connection_type' => 'webhook',
                'oauth' => false,
                'configured_message' => 'Integration enabled. Configure TikTok Events Manager or an automation tool to POST leads to the URLs on the right.',
                'setup_steps' => [
                    'In TikTok Ads Manager, open your instant form and set up webhook or CRM integration.',
                    'Paste the webhook URL or direct ingest URL from this page.',
                    'Map TikTok form fields to campaign fields (email, firstname, phone1, etc.).',
                    'Submit a test lead and verify it appears under Leads.',
                ],
                'show_verify_token' => false,
                'verify_token_label' => null,
                'verify_token_help' => null,
                'show_page_access_token' => false,
                'page_access_token_label' => null,
                'page_access_token_help' => null,
                'webhook_help' => 'Receive POST payloads from TikTok lead-gen webhooks or a forwarding service.',
                'ingest_help' => 'POST JSON with campaign field names for testing, Zapier, Make, or custom integrations.',
                'requires_verify_token' => false,
            ],
        ];
    }
}
