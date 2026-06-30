<?php

namespace App\Http\Controllers\Admin;

use App\Jobs\SendBulkCampaignJob;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BulkSmsCampaign;
use App\Models\Lead;
use App\Models\MessageTemplate;
use App\Models\Segment;
use App\Models\SendingProfile;
use App\Services\Messaging\DeliverabilityReportService;
use App\Services\Messaging\ListHygieneService;
use App\Services\Messaging\MessagingCredentialsResolver;
use App\Services\Messaging\SegmentService;
use App\Services\Messaging\SendTimeOptimizer;
use App\Services\Messaging\SmsShortlinkService;
use App\Services\Messaging\TemplateRenderService;
use App\Services\Messaging\WarmupGovernor;
use App\Services\Messaging\ThrottleGovernor;
use App\Support\Admin\ResolvesAdminAccount;
use App\Support\Tenancy\AccountContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EDeliveryController extends Controller
{
    use ResolvesAdminAccount;

    public function index(Request $request): Response
    {
        $account = AccountContext::get() ?? $this->resolveAdminAccount($request);
        $accountId = $account->id;
        $reports = app(DeliverabilityReportService::class);
        $opsCenter = $reports->opsCenter($accountId, $account);
        $sendTimeOptimizer = app(SendTimeOptimizer::class);
        $shortlinks = app(SmsShortlinkService::class);
        $campaignStats = $reports->campaignStats($accountId);
        $campaignLookup = BulkSmsCampaign::query()
            ->whereIn('id', $campaignStats->pluck('bulk_sms_campaign_id'))
            ->get(['id', 'name', 'channel', 'status'])
            ->keyBy('id');

        return Inertia::render('Admin/EDelivery/Index', [
            'summary' => $opsCenter['summary_30d'],
            'summary7d' => $opsCenter['summary_7d'],
            'summary30d' => $opsCenter['summary_30d'],
            'suppressionCount' => $opsCenter['suppression_count'],
            'deliverabilityAlerts' => $opsCenter['alerts'],
            'alertThresholds' => $reports->thresholds($account),
            'hourlyOpens' => $reports->hourlyOpens($accountId),
            'campaignStats' => $campaignStats->map(fn (array $row) => array_merge($row, [
                'name' => $campaignLookup->get($row['bulk_sms_campaign_id'])?->name ?? 'Campaign #'.$row['bulk_sms_campaign_id'],
                'channel' => $campaignLookup->get($row['bulk_sms_campaign_id'])?->channel ?? 'email',
                'status' => $campaignLookup->get($row['bulk_sms_campaign_id'])?->status,
            ]))->values(),
            'segments' => Segment::orderBy('name')->get(),
            'templates' => MessageTemplate::orderBy('name')->get(),
            'sendingProfiles' => SendingProfile::orderBy('name')->get(),
            'recentCampaigns' => BulkSmsCampaign::with('campaign:id,name')->orderByDesc('created_at')->limit(10)->get(),
            'throttle' => app(WarmupGovernor::class)->status($accountId),
            'warmup' => app(WarmupGovernor::class)->accountWarmupOverview($accountId),
            'reputation' => app(WarmupGovernor::class)->reputationScore($accountId),
            'providers' => [
                'email' => ['smtp', 'sendgrid', 'mailgun', 'postmark', 'resend'],
                'sms' => ['log', 'twilio', 'vonage'],
            ],
            'mergeTags' => TemplateRenderService::availableTags(),
            'defaultPreviewData' => app(TemplateRenderService::class)->defaultPreviewData(),
            'sendTimeSettings' => $sendTimeOptimizer->settings($account),
            'shortlinkSettings' => $shortlinks->settings($account),
            'shortlinkStats' => $shortlinks->stats($accountId),
            'hygieneSettings' => app(ListHygieneService::class)->settings($account),
            'sendingDomainDnsHints' => app(MessagingCredentialsResolver::class)->dnsHintsForSendingDomain('mail.example.com'),
        ]);
    }

    public function updateSendTimeSettings(Request $request, SendTimeOptimizer $optimizer): RedirectResponse
    {
        $account = AccountContext::get() ?? $this->resolveAdminAccount($request);

        $validated = $request->validate([
            'send_time_optimization' => 'required|boolean',
            'quiet_hours_start' => 'required|date_format:H:i',
            'quiet_hours_end' => 'required|date_format:H:i',
            'optimal_send_hour' => 'required|integer|min:0|max:23',
        ]);

        $validated['quiet_hours_start'] = substr((string) $validated['quiet_hours_start'], 0, 5);
        $validated['quiet_hours_end'] = substr((string) $validated['quiet_hours_end'], 0, 5);

        $account->update([
            'settings' => $optimizer->mergeSettingsIntoAccount($account, $validated),
        ]);

        return back()->with('success', 'Send-time optimization settings saved.');
    }

    public function updateShortlinkSettings(Request $request, SmsShortlinkService $shortlinks): RedirectResponse
    {
        $account = AccountContext::get() ?? $this->resolveAdminAccount($request);

        $validated = $request->validate([
            'sms_shortlinks_enabled' => 'required|boolean',
        ]);

        $account->update([
            'settings' => $shortlinks->mergeSettingsIntoAccount($account, $validated),
        ]);

        return back()->with('success', 'SMS short link settings saved.');
    }

    public function updateHygieneSettings(Request $request, ListHygieneService $hygiene): RedirectResponse
    {
        $account = AccountContext::get() ?? $this->resolveAdminAccount($request);

        $validated = $request->validate([
            'list_hygiene_enabled' => 'required|boolean',
            'inactive_days_threshold' => 'required|integer|min:1|max:3650',
            'hygiene_auto_suppress_bounces' => 'required|boolean',
        ]);

        $account->update([
            'settings' => $hygiene->mergeSettingsIntoAccount($account, $validated),
        ]);

        return back()->with('success', 'List hygiene settings saved.');
    }

    public function runHygiene(Request $request, ListHygieneService $hygiene): RedirectResponse
    {
        $account = AccountContext::get() ?? $this->resolveAdminAccount($request);
        $dryRun = $request->boolean('dry_run');
        $result = $hygiene->run($account, $dryRun, force: true);

        $prefix = $dryRun ? 'Dry-run: ' : '';

        return back()->with('success', sprintf(
            '%sTagged %d bounced and %d inactive lead(s).',
            $prefix,
            $result['bounces_tagged'],
            $result['inactive_tagged'],
        ));
    }

    public function pauseSending(Request $request, ThrottleGovernor $throttle): RedirectResponse
    {
        $accountId = AccountContext::id() ?? $this->resolveAdminAccount($request)->id;
        $throttle->pauseSending($accountId);

        return back()->with('success', 'Marketing sends paused for this platform.');
    }

    public function resumeSending(Request $request, ThrottleGovernor $throttle): RedirectResponse
    {
        $accountId = AccountContext::id() ?? $this->resolveAdminAccount($request)->id;
        $throttle->resumeSending($accountId);

        return back()->with('success', 'Marketing sends resumed.');
    }

    public function storeBulkCampaign(Request $request): RedirectResponse
    {
        $validated = $this->validatedBulkCampaign($request);
        $accountId = AccountContext::id() ?? $this->resolveAdminAccount($request)->id;

        $this->applyMessageTemplate($validated, $accountId);

        $campaign = BulkSmsCampaign::create(array_merge($validated, [
            'account_id' => $accountId,
            'status' => ! empty($validated['scheduled_at']) ? 'scheduled' : 'draft',
        ]));

        if (empty($validated['scheduled_at'])) {
            SendBulkCampaignJob::dispatch($campaign->id);
        }

        return back()->with('success', 'Bulk campaign created.');
    }

    public function sendBulkCampaign(BulkSmsCampaign $bulkSms): RedirectResponse
    {
        SendBulkCampaignJob::dispatch($bulkSms->id);

        return back()->with('success', 'Bulk campaign queued for sending.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedBulkCampaign(Request $request): array
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'channel' => 'nullable|in:sms,email,both',
            'subject' => 'nullable|string|max:255',
            'provider' => 'nullable|string|max:64',
            'message' => 'required|string|max:16000',
            'html_body' => 'nullable|string',
            'message_template_id' => 'nullable|exists:message_templates,id',
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

        $validated['channel'] = $validated['channel'] ?? BulkSmsCampaign::CHANNEL_SMS;

        if (in_array($validated['channel'], [BulkSmsCampaign::CHANNEL_EMAIL, BulkSmsCampaign::CHANNEL_BOTH], true)
            && empty($validated['subject'])) {
            $validated['subject'] = $validated['name'];
        }

        unset($validated['message_template_id']);

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function applyMessageTemplate(array &$validated, int $accountId): void
    {
        $templateId = request()->input('message_template_id');

        if (! $templateId) {
            return;
        }

        $template = MessageTemplate::query()
            ->where('account_id', $accountId)
            ->find($templateId);

        if (! $template) {
            throw ValidationException::withMessages([
                'message_template_id' => 'Template not found for this platform.',
            ]);
        }

        $validated['subject'] = $validated['subject'] ?? $template->subject;
        $validated['message'] = $template->body ?? $validated['message'];
        $validated['html_body'] = $validated['html_body'] ?? $template->html_body;
    }

    public function storeSegment(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'campaign_id' => 'nullable|exists:campaigns,id',
            'type' => 'nullable|in:static,dynamic',
            'rules' => 'nullable|array',
            'rules.tags' => 'nullable|array',
            'rules.status' => 'nullable|string',
            'rules.days' => 'nullable|integer|min:1',
            'rules.has_email' => 'nullable|boolean',
            'rules.has_phone' => 'nullable|boolean',
            'rules.opened_last_days' => 'nullable|integer|min:1',
            'rules.clicked_last_days' => 'nullable|integer|min:1',
            'rules.never_opened' => 'nullable|boolean',
        ]);

        Segment::create($validated);

        return back()->with('success', 'Segment created.');
    }

    public function destroySegment(Segment $segment): RedirectResponse
    {
        $segment->delete();

        return back()->with('success', 'Segment removed.');
    }

    public function storeTemplate(Request $request): RedirectResponse
    {
        MessageTemplate::create($this->validatedTemplate($request));

        return back()->with('success', 'Template saved.');
    }

    public function updateTemplate(Request $request, MessageTemplate $template): RedirectResponse
    {
        $template->update($this->validatedTemplate($request));

        return back()->with('success', 'Template updated.');
    }

    public function previewTemplate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'subject' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'html_body' => 'nullable|string',
            'preview_data' => 'nullable|array',
        ]);

        $rendered = app(TemplateRenderService::class)->renderParts(
            $validated,
            null,
            $validated['preview_data'] ?? null,
        );

        return response()->json($rendered);
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedTemplate(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'channel' => 'nullable|in:email,sms',
            'subject' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'html_body' => 'nullable|string',
            'preview_data' => 'nullable|array',
        ]);
    }

    public function destroyTemplate(MessageTemplate $template): RedirectResponse
    {
        $template->delete();

        return back()->with('success', 'Template removed.');
    }

    public function storeSendingProfile(Request $request): RedirectResponse
    {
        $accountId = AccountContext::id() ?? $this->resolveAdminAccount($request)->id;
        $validated = $request->validate($this->sendingProfileRules($accountId));

        if (! empty($validated['is_default'])) {
            SendingProfile::query()->where('account_id', $accountId)->update(['is_default' => false]);
        }

        if (! empty($validated['warmup_enabled'])) {
            $validated['warmup_started_at'] = now();
        }

        SendingProfile::create($validated);

        return back()->with('success', 'Sending profile created.');
    }

    public function updateSendingProfile(Request $request, SendingProfile $profile): RedirectResponse
    {
        $accountId = AccountContext::id() ?? $this->resolveAdminAccount($request)->id;
        $validated = $request->validate($this->sendingProfileUpdateRules($accountId, $profile));

        if (array_key_exists('is_default', $validated) && $validated['is_default']) {
            SendingProfile::query()
                ->where('account_id', $accountId)
                ->where('id', '!=', $profile->id)
                ->update(['is_default' => false]);
        }

        if (array_key_exists('warmup_enabled', $validated)) {
            $wasEnabled = (bool) $profile->warmup_enabled;
            $enabling = $validated['warmup_enabled'] && ! $wasEnabled;

            if ($enabling) {
                $validated['warmup_started_at'] = now();
            } elseif (! $validated['warmup_enabled']) {
                $validated['warmup_started_at'] = null;
            }
        }

        $profile->update($validated);

        return back()->with('success', 'Sending profile updated.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function sendingProfileRules(int $accountId): array
    {
        return [
            'name' => 'required|string|max:255',
            'provider' => 'required|string|max:32',
            'domain_match' => 'nullable|string|max:255',
            'sending_domain' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i',
                Rule::unique('sending_profiles', 'sending_domain')->where(fn ($query) => $query->where('account_id', $accountId)),
            ],
            'from_name' => 'nullable|string|max:255',
            'from_email' => 'nullable|email|max:255',
            'reply_to' => 'nullable|email|max:255',
            'is_default' => 'nullable|boolean',
            'warmup_enabled' => 'nullable|boolean',
            'warmup_day_one_limit' => 'nullable|integer|min:1|max:100000',
            'warmup_target_limit' => 'nullable|integer|min:1|max:1000000',
            'warmup_ramp_days' => 'nullable|integer|min:1|max:90',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function sendingProfileUpdateRules(int $accountId, SendingProfile $profile): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'provider' => 'sometimes|required|string|max:32',
            'domain_match' => 'nullable|string|max:255',
            'sending_domain' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/i',
                Rule::unique('sending_profiles', 'sending_domain')
                    ->where(fn ($query) => $query->where('account_id', $accountId))
                    ->ignore($profile->id),
            ],
            'from_name' => 'nullable|string|max:255',
            'from_email' => 'nullable|email|max:255',
            'reply_to' => 'nullable|email|max:255',
            'is_default' => 'nullable|boolean',
            'warmup_enabled' => 'nullable|boolean',
            'warmup_day_one_limit' => 'nullable|integer|min:1|max:100000',
            'warmup_target_limit' => 'nullable|integer|min:1|max:1000000',
            'warmup_ramp_days' => 'nullable|integer|min:1|max:90',
        ];
    }

    public function updateSendingProfileWarmup(Request $request, SendingProfile $profile): RedirectResponse
    {
        $validated = $request->validate([
            'warmup_enabled' => 'required|boolean',
            'warmup_day_one_limit' => 'nullable|integer|min:1|max:100000',
            'warmup_target_limit' => 'nullable|integer|min:1|max:1000000',
            'warmup_ramp_days' => 'nullable|integer|min:1|max:90',
        ]);

        $wasEnabled = (bool) $profile->warmup_enabled;
        $enabling = $validated['warmup_enabled'] && ! $wasEnabled;

        $profile->update(array_merge($validated, $enabling ? ['warmup_started_at' => now()] : []));

        if (! $validated['warmup_enabled']) {
            $profile->update(['warmup_started_at' => null]);
        }

        return back()->with('success', $validated['warmup_enabled'] ? 'Domain warmup enabled.' : 'Domain warmup disabled.');
    }

    public function destroySendingProfile(SendingProfile $profile): RedirectResponse
    {
        $profile->delete();

        return back()->with('success', 'Sending profile removed.');
    }

    public function tagLead(Request $request, Lead $lead): RedirectResponse
    {
        $validated = $request->validate(['tag' => 'required|string|max:64']);

        app(SegmentService::class)->tagLead($lead, $validated['tag']);

        return back()->with('success', 'Tag added.');
    }

    public function untagLead(Request $request, Lead $lead): RedirectResponse
    {
        $validated = $request->validate(['tag' => 'required|string|max:64']);

        app(SegmentService::class)->untagLead($lead, $validated['tag']);

        return back()->with('success', 'Tag removed.');
    }
}
