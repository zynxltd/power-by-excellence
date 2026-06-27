<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\Webhook;
use App\Support\Admin\ResolvesAdminAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    use ResolvesAdminAccount;

    public function index(Request $request): Response
    {
        $webhookCount = Webhook::count();
        $apiKeyCount = ApiKey::where('is_active', true)->count();

        $account = null;

        try {
            $account = $this->resolveAdminAccount($request);
            $settings = $account->settings ?? [];
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException) {
            $settings = [];
        }

        $fraud = $account
            ? app(\App\Services\Billing\FraudProtectionService::class)->summary($account)
            : ['entitled' => false, 'can_validate' => false];

        $leadSources = $settings['lead_sources'] ?? [];
        $validation = $settings['validation_integration'] ?? [];
        $validationProvider = $validation['provider'] ?? config('validation.driver', 'demo');
        $hasIpqsKey = filled(app(\App\Services\Validation\ValidationProviderResolver::class)->ipqsConfig($account)['api_key'] ?? null);
        $validationConnected = $fraud['entitled'] && $validationProvider === 'ipqs' && $hasIpqsKey;
        $validationStatus = match (true) {
            ! $fraud['entitled'] => 'upgrade',
            ($fraud['cap_reached'] ?? false) => 'cap_reached',
            $validationConnected => 'connected',
            default => 'available',
        };

        $integrations = [
            [
                'id' => 'webhooks',
                'name' => 'Outbound Webhooks',
                'category' => 'Connectivity',
                'description' => 'Push lead events to CRMs, Slack, Zapier, or custom endpoints when leads are sold or updated.',
                'status' => $webhookCount > 0 ? 'connected' : 'available',
                'route' => 'webhooks.index',
                'icon' => 'webhook',
            ],
            [
                'id' => 'api_keys',
                'name' => 'REST API Keys',
                'category' => 'Connectivity',
                'description' => 'Authenticate lead ingest, buyer credit, and admin API calls from your systems.',
                'status' => $apiKeyCount > 0 ? 'connected' : 'available',
                'route' => 'api-keys.index',
                'icon' => 'api',
            ],
            [
                'id' => 'email_validation',
                'name' => 'Fraud Detection',
                'category' => 'Validation',
                'description' => 'Email, phone, IP/proxy/VPN, and URL fraud checks on lead ingest.',
                'status' => $validationStatus,
                'route' => 'integrations.validation',
                'icon' => 'validation',
            ],
            [
                'id' => 'facebook',
                'name' => 'Facebook Lead Ads',
                'category' => 'Lead Sources',
                'description' => 'Meta webhook + Page token. Receive Lead Ad form submissions into campaigns.',
                'status' => ($leadSources['facebook']['enabled'] ?? false) ? 'connected' : 'available',
                'route' => 'integrations.lead-source',
                'route_params' => ['provider' => 'facebook'],
                'icon' => 'facebook',
            ],
            [
                'id' => 'google',
                'name' => 'Google Ads Lead Forms',
                'category' => 'Lead Sources',
                'description' => 'Webhook or Zapier/Make ingest. Import Google Ads lead form submissions.',
                'status' => ($leadSources['google']['enabled'] ?? false) ? 'connected' : 'available',
                'route' => 'integrations.lead-source',
                'route_params' => ['provider' => 'google'],
                'icon' => 'google',
            ],
            [
                'id' => 'tiktok',
                'name' => 'TikTok Lead Gen',
                'category' => 'Lead Sources',
                'description' => 'Webhook or direct ingest. Sync TikTok instant form leads into campaigns.',
                'status' => ($leadSources['tiktok']['enabled'] ?? false) ? 'connected' : 'available',
                'route' => 'integrations.lead-source',
                'route_params' => ['provider' => 'tiktok'],
                'icon' => 'tiktok',
            ],
            [
                'id' => 'hosted_forms',
                'name' => 'Form Builder',
                'category' => 'Lead Sources',
                'description' => 'Hosted capture forms with domain lock, custom CSS, and conditional redirects.',
                'status' => 'available',
                'route' => 'forms.index',
                'icon' => 'form',
            ],
            [
                'id' => 'zapier',
                'name' => 'Zapier',
                'category' => 'Automation',
                'description' => 'Connect 5,000+ apps via webhooks - use Outbound Webhooks as your Zapier trigger.',
                'status' => $webhookCount > 0 ? 'connected' : 'available',
                'route' => 'webhooks.index',
                'icon' => 'zapier',
            ],
        ];

        return Inertia::render('Admin/Integrations/Index', [
            'integrations' => $integrations,
            'stats' => [
                'connected' => collect($integrations)->where('status', 'connected')->count(),
                'available' => collect($integrations)->where('status', 'available')->count(),
                'coming_soon' => collect($integrations)->where('status', 'coming_soon')->count(),
            ],
        ]);
    }
}
