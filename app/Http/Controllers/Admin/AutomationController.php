<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendBulkCampaignJob;
use App\Models\AutomationSequence;
use App\Models\AutomationSequenceStep;
use App\Models\BulkSmsCampaign;
use App\Models\Campaign;
use App\Models\Delivery;
use App\Models\DistributionConfig;
use App\Models\EventAlert;
use App\Models\EventAlertFire;
use App\Models\Segment;
use App\Models\SendingProfile;
use App\Models\MessageTemplate;
use App\Services\Automation\AutomationSequenceService;
use App\Support\Tenancy\AccountContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AutomationController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Admin/Automation/Index', [
            'sequences' => AutomationSequence::with('steps')->with(['campaign:id,name', 'segment:id,name'])->orderBy('name')->get(),
            'bulkCampaigns' => BulkSmsCampaign::with('campaign:id,name')->orderByDesc('created_at')->limit(20)->get(),
            'segments' => Segment::orderBy('name')->get(['id', 'name']),
            'sendingProfiles' => SendingProfile::orderBy('name')->get(['id', 'name']),
            'templates' => MessageTemplate::orderBy('name')->get(['id', 'name', 'channel']),
            'eventAlerts' => EventAlert::orderBy('name')->get(),
            'campaigns' => Campaign::orderBy('name')->get(['id', 'name', 'reference', 'use_advanced_distribution']),
            'routingOverview' => $this->routingOverview(),
            'metrics' => collect(EventAlertService::metricLabels())
                ->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
                ->values()
                ->all(),
            'providers' => [
                'sms' => ['log', 'twilio', 'vonage'],
                'email' => ['smtp', 'sendgrid', 'mailgun', 'postmark', 'resend'],
            ],
            'alertChannels' => ['email', 'sms', 'webhook', 'slack'],
            'recentAlertFires' => EventAlertFire::query()
                ->when(
                    AccountContext::id() ?? $request->attributes->get('account')?->id,
                    fn ($query, int $accountId) => $query->where('account_id', $accountId),
                )
                ->with(['alert:id,name', 'account:id,name'])
                ->orderByDesc('created_at')
                ->limit(25)
                ->get(),
        ]);
    }

    public function storeSequence(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->sequenceRules());

        $accountId = $this->resolveAutomationAccountId($validated['campaign_id'] ?? null);

        $sequence = AutomationSequence::create([
            'account_id' => $accountId,
            'name' => $validated['name'],
            'campaign_id' => $validated['campaign_id'] ?? null,
            'segment_id' => $validated['segment_id'] ?? null,
            'trigger_event' => $validated['trigger_event'],
            'status' => 'active',
        ]);

        $this->syncSequenceSteps($sequence, $validated['steps']);

        return back()->with('success', 'Automation sequence created.');
    }

    public function updateSequence(Request $request, AutomationSequence $sequence): RedirectResponse
    {
        $validated = $request->validate($this->sequenceRules());

        $accountId = $this->resolveAutomationAccountId($validated['campaign_id'] ?? null, $sequence->account_id);

        $sequence->update([
            'account_id' => $accountId,
            'name' => $validated['name'],
            'campaign_id' => $validated['campaign_id'] ?? null,
            'segment_id' => $validated['segment_id'] ?? null,
            'trigger_event' => $validated['trigger_event'],
            'status' => $validated['status'] ?? $sequence->status ?? 'active',
        ]);

        $sequence->steps()->delete();
        $this->syncSequenceSteps($sequence, $validated['steps']);

        return back()->with('success', 'Automation sequence updated.');
    }

    public function processJourneys(AutomationSequenceService $sequences): RedirectResponse
    {
        $count = $sequences->processDueEnrollments();

        return back()->with('success', "Processed {$count} journey enrollment(s).");
    }

    /**
     * @return array<string, mixed>
     */
    protected function sequenceRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'segment_id' => 'nullable|exists:segments,id',
            'trigger_event' => 'required|in:on_lead_received,on_lead_sold,on_lead_unsold,on_segment_entry',
            'status' => 'nullable|in:active,inactive',
            'steps' => 'required|array|min:1',
            'steps.*.delay_minutes' => 'integer|min:0',
            'steps.*.action' => 'required|in:send,send_template,wait',
            'steps.*.channel' => 'nullable|in:email,sms',
            'steps.*.config' => 'nullable|array',
            'steps.*.config.subject' => 'nullable|string|max:255',
            'steps.*.config.body' => 'nullable|string',
            'steps.*.config.to_field' => 'nullable|string|max:64',
            'steps.*.config.provider' => 'nullable|string|max:64',
            'steps.*.config.message_template_id' => 'nullable|integer|exists:message_templates,id',
            'steps.*.config.sending_profile_id' => 'nullable|integer|exists:sending_profiles,id',
            'steps.*.config.branch' => 'nullable|in:opened,clicked,not_opened',
        ];
    }

    protected function syncSequenceSteps(AutomationSequence $sequence, array $steps): void
    {
        foreach ($steps as $i => $step) {
            $action = $step['action'] ?? 'send';
            $channel = $step['channel'] ?? ($action === 'wait' ? 'email' : 'email');

            if ($action !== 'wait' && empty($channel)) {
                $channel = 'email';
            }

            AutomationSequenceStep::create([
                'automation_sequence_id' => $sequence->id,
                'sort_order' => $i,
                'action' => $action,
                'delay_minutes' => $step['delay_minutes'] ?? 0,
                'channel' => $channel,
                'config' => $step['config'] ?? [],
            ]);
        }
    }

    protected function resolveAutomationAccountId(?int $campaignId = null, ?int $fallbackAccountId = null): int
    {
        if ($accountId = AccountContext::id()) {
            return $accountId;
        }

        if ($campaignId) {
            $campaignAccountId = Campaign::query()->whereKey($campaignId)->value('account_id');
            if ($campaignAccountId) {
                return (int) $campaignAccountId;
            }
        }

        if ($fallbackAccountId) {
            return $fallbackAccountId;
        }

        if ($sessionAccountId = session('current_account_id')) {
            return (int) $sessionAccountId;
        }

        throw ValidationException::withMessages([
            'campaign_id' => 'Select a tenant or choose a campaign so this automation belongs to a platform.',
        ]);
    }

    public function storeBulkSms(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'channel' => 'nullable|in:sms,email',
            'subject' => 'nullable|string|max:255',
            'provider' => 'nullable|string|max:64',
            'message' => 'required|string|max:16000',
            'html_body' => 'nullable|string',
            'segment_id' => 'nullable|exists:segments,id',
            'sending_profile_id' => 'nullable|exists:sending_profiles,id',
            'throttle_per_minute' => 'nullable|integer|min:1|max:1000',
            'ab_test' => 'nullable|array',
            'ab_test.split_percent' => 'nullable|integer|min:5|max:50',
            'ab_test.wait_minutes' => 'nullable|integer|min:5|max:1440',
            'ab_test.winner_metric' => 'nullable|in:open,click',
            'ab_test.variant_a' => 'nullable|array',
            'ab_test.variant_b' => 'nullable|array',
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
            'account_id' => $this->resolveAutomationAccountId($validated['campaign_id'] ?? null),
            'status' => ! empty($validated['scheduled_at']) ? 'scheduled' : 'draft',
        ]));

        if (empty($validated['scheduled_at'])) {
            SendBulkCampaignJob::dispatch($campaign->id);
        }

        return back()->with('success', 'Bulk campaign created.');
    }

    public function sendBulkSms(BulkSmsCampaign $bulkSms): RedirectResponse
    {
        SendBulkCampaignJob::dispatch($bulkSms->id);

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
