<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BulkSmsCampaign;
use App\Models\Lead;
use App\Models\MessageTemplate;
use App\Models\Segment;
use App\Models\SendingProfile;
use App\Services\Messaging\DeliverabilityReportService;
use App\Services\Messaging\SegmentService;
use App\Support\Admin\ResolvesAdminAccount;
use App\Support\Tenancy\AccountContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EDeliveryController extends Controller
{
    use ResolvesAdminAccount;

    public function index(Request $request): Response
    {
        $accountId = AccountContext::id() ?? $this->resolveAdminAccount($request)->id;
        $reports = app(DeliverabilityReportService::class);

        return Inertia::render('Admin/EDelivery/Index', [
            'summary' => $reports->summary($accountId),
            'hourlyOpens' => $reports->hourlyOpens($accountId),
            'campaignStats' => $reports->campaignStats($accountId),
            'segments' => Segment::orderBy('name')->get(),
            'templates' => MessageTemplate::orderBy('name')->get(),
            'sendingProfiles' => SendingProfile::orderBy('name')->get(),
            'recentCampaigns' => BulkSmsCampaign::with('campaign:id,name')->orderByDesc('created_at')->limit(10)->get(),
            'providers' => [
                'email' => ['smtp', 'sendgrid', 'mailgun', 'postmark', 'resend'],
                'sms' => ['log', 'twilio', 'vonage'],
            ],
        ]);
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
        MessageTemplate::create($request->validate([
            'name' => 'required|string|max:255',
            'channel' => 'nullable|in:email,sms',
            'subject' => 'nullable|string|max:255',
            'body' => 'nullable|string',
            'html_body' => 'nullable|string',
        ]));

        return back()->with('success', 'Template saved.');
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
