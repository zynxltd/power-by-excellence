<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\BulkSmsCampaign;
use App\Models\Lead;
use App\Models\MessageTemplate;
use App\Models\Segment;
use App\Models\SendingProfile;
use App\Services\Messaging\DeliverabilityReportService;
use App\Services\Messaging\SegmentService;
use App\Services\Messaging\TemplateRenderService;
use App\Services\Messaging\ThrottleGovernor;
use App\Support\Admin\ResolvesAdminAccount;
use App\Support\Tenancy\AccountContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
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
            'throttle' => app(ThrottleGovernor::class)->status($accountId),
            'providers' => [
                'email' => ['smtp', 'sendgrid', 'mailgun', 'postmark', 'resend'],
                'sms' => ['log', 'twilio', 'vonage'],
            ],
            'mergeTags' => TemplateRenderService::availableTags(),
            'defaultPreviewData' => app(TemplateRenderService::class)->defaultPreviewData(),
        ]);
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
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'provider' => 'required|string|max:32',
            'domain_match' => 'nullable|string|max:255',
            'from_name' => 'nullable|string|max:255',
            'from_email' => 'nullable|email|max:255',
            'reply_to' => 'nullable|email|max:255',
            'is_default' => 'nullable|boolean',
        ]);

        if (! empty($validated['is_default'])) {
            SendingProfile::query()->update(['is_default' => false]);
        }

        SendingProfile::create($validated);

        return back()->with('success', 'Sending profile created.');
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
