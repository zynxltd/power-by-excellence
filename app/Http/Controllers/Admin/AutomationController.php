<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AutomationSequence;
use App\Models\AutomationSequenceStep;
use App\Models\BulkSmsCampaign;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DistributionConfig;
use App\Models\EventAlert;
use App\Services\Automation\BulkSmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AutomationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Automation/Index', [
            'sequences' => AutomationSequence::with('steps')->with('campaign:id,name')->orderBy('name')->get(),
            'bulkCampaigns' => BulkSmsCampaign::with('campaign:id,name')->orderByDesc('created_at')->limit(20)->get(),
            'eventAlerts' => EventAlert::orderBy('name')->get(),
            'campaigns' => Campaign::orderBy('name')->get(['id', 'name', 'reference', 'use_advanced_distribution']),
            'routingOverview' => $this->routingOverview(),
            'metrics' => [
                ['value' => 'leads_today', 'label' => 'Leads today'],
                ['value' => 'sold_today', 'label' => 'Sold today'],
                ['value' => 'unsold_today', 'label' => 'Unsold today'],
                ['value' => 'reject_rate_24h', 'label' => 'Reject rate (24h)'],
                ['value' => 'delivery_success_rate_24h', 'label' => 'Delivery success (24h)'],
                ['value' => 'pending_queue', 'label' => 'Pending queue'],
                ['value' => 'quarantined_count', 'label' => 'Quarantined'],
                ['value' => 'avg_processing_ms_24h', 'label' => 'Avg processing (ms)'],
                ['value' => 'caps_near_limit', 'label' => 'Buyers near cap'],
            ],
            'providers' => [
                'sms' => ['log', 'twilio', 'vonage'],
                'email' => ['smtp', 'sendgrid', 'mailgun', 'postmark', 'resend'],
            ],
            'alertChannels' => ['email', 'sms', 'webhook', 'slack'],
            'recentAlertFires' => \App\Models\EventAlertFire::orderByDesc('created_at')->limit(10)->get(),
        ]);
    }

    public function storeSequence(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'trigger_event' => 'required|in:on_lead_received,on_lead_sold,on_lead_unsold',
            'steps' => 'required|array|min:1',
            'steps.*.delay_minutes' => 'integer|min:0',
            'steps.*.channel' => 'required|in:email,sms',
            'steps.*.config' => 'nullable|array',
        ]);

        $sequence = AutomationSequence::create([
            'name' => $validated['name'],
            'campaign_id' => $validated['campaign_id'] ?? null,
            'trigger_event' => $validated['trigger_event'],
            'status' => 'active',
        ]);

        foreach ($validated['steps'] as $i => $step) {
            AutomationSequenceStep::create([
                'automation_sequence_id' => $sequence->id,
                'sort_order' => $i,
                'delay_minutes' => $step['delay_minutes'] ?? 0,
                'channel' => $step['channel'],
                'config' => $step['config'] ?? [],
            ]);
        }

        return back()->with('success', 'Automation sequence created.');
    }

    public function storeBulkSms(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'channel' => 'nullable|in:sms,email',
            'subject' => 'nullable|string|max:255',
            'provider' => 'nullable|string|max:64',
            'message' => 'required|string|max:1600',
            'filter' => 'nullable|array',
            'filter.has_email' => 'nullable|boolean',
            'filter.has_phone' => 'nullable|boolean',
            'scheduled_at' => 'nullable|date',
        ]);

        $validated['channel'] = $validated['channel'] ?? 'sms';

        if ($validated['channel'] === 'email' && empty($validated['subject'])) {
            $validated['subject'] = $validated['name'];
        }

        $campaign = BulkSmsCampaign::create(array_merge($validated, [
            'status' => ! empty($validated['scheduled_at']) ? 'scheduled' : 'draft',
        ]));

        if (empty($validated['scheduled_at'])) {
            app(BulkSmsService::class)->send($campaign);
        }

        return back()->with('success', 'Bulk campaign created.');
    }

    public function sendBulkSms(BulkSmsCampaign $bulkSms): RedirectResponse
    {
        app(BulkSmsService::class)->send($bulkSms);

        return back()->with('success', 'Bulk SMS sent.');
    }

    public function storeAlert(Request $request): RedirectResponse
    {
        EventAlert::create($request->validate([
            'name' => 'required|string|max:255',
            'metric' => 'required|string',
            'operator' => 'required|in:lt,lte,gt,gte,eq',
            'threshold' => 'required|numeric',
            'channel' => 'required|in:email,sms,webhook,slack',
            'config' => 'nullable|array',
            'config.email' => 'nullable|email',
            'config.phone' => 'nullable|string|max:32',
            'config.webhook_url' => 'nullable|url',
            'config.slack_webhook' => 'nullable|url',
            'config.provider' => 'nullable|string|max:64',
            'config.cooldown_minutes' => 'nullable|integer|min:5|max:1440',
        ]));

        return back()->with('success', 'Event alert created.');
    }

    public function destroySequence(AutomationSequence $sequence): RedirectResponse
    {
        $sequence->delete();

        return back()->with('success', 'Sequence removed.');
    }

    public function destroyAlert(EventAlert $alert): RedirectResponse
    {
        $alert->delete();

        return back()->with('success', 'Alert removed.');
    }

    protected function routingOverview(): array
    {
        $configs = DistributionConfig::with('campaign:id,name,reference')
            ->forTenant()
            ->where('is_active', true)
            ->get();

        return $configs->map(function (DistributionConfig $config) {
            $groups = $config->config['groups'] ?? [];
            $deliveryIds = collect($groups)->flatMap(fn ($g) => $g['delivery_ids'] ?? [])->unique();
            $deliveries = Delivery::forTenant()->with('buyer:id,name')->whereIn('id', $deliveryIds)->get()->keyBy('id');

            $tiers = collect($groups)->map(function (array $group, int $i) use ($deliveries) {
                $ids = $group['delivery_ids'] ?? [];
                $tierDeliveries = collect($ids)->map(fn ($id) => $deliveries->get($id))->filter();

                return [
                    'tier' => $i + 1,
                    'name' => $group['name'] ?? 'Tier '.($i + 1),
                    'mode' => $group['mode'] ?? 'waterfall',
                    'floor_price' => $group['floor_price'] ?? null,
                    'delivery_count' => $tierDeliveries->count(),
                    'deliveries' => $tierDeliveries->map(fn (Delivery $d) => [
                        'id' => $d->id,
                        'name' => $d->name,
                        'revenue_type' => $d->revenue_type,
                        'revenue_amount' => $d->revenue_amount,
                        'buyer' => $d->buyer?->name,
                    ])->values(),
                ];
            })->values();

            return [
                'config_id' => $config->id,
                'config_name' => $config->name,
                'campaign' => $config->campaign?->only(['id', 'name', 'reference']),
                'tier_count' => $tiers->count(),
                'tiers' => $tiers,
            ];
        })->values()->all();
    }
}
