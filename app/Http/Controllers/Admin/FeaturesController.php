<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ApiKey;
use App\Models\AutoResponder;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DistributionConfig;
use App\Models\LeadImport;
use App\Models\TrackingClick;
use App\Models\TrackingConversion;
use App\Models\TrackingLink;
use App\Models\Webhook;
use App\Services\Delivery\DeliveryAnalyticsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FeaturesController extends Controller
{
    public function index(DeliveryAnalyticsService $analytics): Response
    {
        return Inertia::render('Admin/Features/Index', [
            'stats' => [
                'campaigns' => Campaign::count(),
                'deliveries' => Delivery::where('status', 'active')->count(),
                'ping_trees' => DistributionConfig::forTenant()->where('is_active', true)->count(),
                'webhooks' => Webhook::count(),
                'api_keys' => ApiKey::where('is_active', true)->count(),
                'imports' => LeadImport::count(),
                'auto_responders' => AutoResponder::where('status', 'active')->count(),
                'tracking_links' => TrackingLink::count(),
                'clicks_today' => TrackingClick::where('clicked_at', '>=', today())->count(),
                'conversions_pending' => TrackingConversion::where('status', TrackingConversion::STATUS_PENDING)->count(),
                ...$analytics->platformSummary(),
            ],
        ]);
    }

    public function capture(): Response
    {
        return Inertia::render('Admin/Features/Capture', [
            'links' => $this->featureLinks()['capture'],
        ]);
    }

    public function validation(): Response
    {
        $campaigns = Campaign::orderBy('name')->get(['id', 'name', 'reference', 'validation_config', 'dedupe_config']);

        return Inertia::render('Admin/Features/Validation', [
            'campaigns' => $campaigns,
            'links' => $this->featureLinks()['validation'],
        ]);
    }

    public function routing(): Response
    {
        return Inertia::render('Admin/Features/Routing', [
            'configs' => DistributionConfig::forTenant()->with('campaign:id,name')->orderByDesc('updated_at')->get(),
            'links' => $this->featureLinks()['routing'],
        ]);
    }

    public function delivery(): Response
    {
        return Inertia::render('Admin/Features/Delivery', [
            'deliveries' => Delivery::forTenant()->with(['campaign', 'buyer'])->orderByDesc('updated_at')->limit(12)->get(),
            'links' => $this->featureLinks()['delivery'],
        ]);
    }

    protected function featureLinks(): array
    {
        return [
            'capture' => [
                ['label' => 'API Keys', 'route' => 'api-keys.index', 'desc' => 'REST API lead ingest'],
                ['label' => 'Webhooks', 'route' => 'webhooks.index', 'desc' => 'Inbound/outbound event sync'],
                ['label' => 'CSV Import', 'route' => 'imports.index', 'desc' => 'Batch file imports'],
                ['label' => 'Integrations', 'route' => 'integrations.index', 'desc' => 'Facebook, Google, TikTok (roadmap)'],
                ['label' => 'Suppliers', 'route' => 'suppliers.index', 'desc' => 'Affiliate / publisher ingest'],
            ],
            'validation' => [
                ['label' => 'Campaign settings', 'route' => 'campaigns.index', 'desc' => 'Per-campaign validation & dedupe'],
                ['label' => 'Fraud detection', 'route' => 'leads.index', 'desc' => 'Quarantined leads queue'],
                ['label' => 'Lead pipeline', 'route' => 'leads.index', 'desc' => 'Rejected lead reasons'],
            ],
            'routing' => [
                ['label' => 'Ping Tree', 'route' => 'distribution.index', 'desc' => 'Tiered hybrid routing'],
                ['label' => 'Routing Simulator', 'route' => 'routing.simulator', 'desc' => 'Dry-run routing decisions'],
                ['label' => 'Deliveries', 'route' => 'deliveries.index', 'desc' => 'Buyer endpoints & methods'],
            ],
            'delivery' => [
                ['label' => 'All deliveries', 'route' => 'deliveries.index', 'desc' => 'API, ping-post, email, SMS, store'],
                ['label' => 'Live operations', 'route' => 'operations.index', 'desc' => 'Real-time delivery logs'],
                ['label' => 'Buyer portal', 'route' => 'buyers.index', 'desc' => 'Buyer lead access'],
            ],
        ];
    }
}
