<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AutoResponder;
use App\Models\AutomationSequence;
use App\Models\Campaign;
use App\Services\Automation\AutoResponderService;
use App\Support\Tenancy\AccountContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AutoResponderController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Features/AutoResponders', [
            'responders' => AutoResponder::with('campaign:id,name')->orderBy('name')->get(),
            'sequences' => AutomationSequence::with('steps')->with('campaign:id,name')->orderBy('name')->get(),
            'campaigns' => Campaign::orderBy('name')->get(['id', 'name', 'reference']),
            'providers' => [
                'sms' => ['log', 'twilio', 'vonage'],
                'email' => ['smtp', 'sendgrid', 'mailgun', 'postmark', 'resend'],
            ],
            'providerStatus' => $this->providerStatus(),
            'testResult' => session('autoResponderTestResult'),
        ]);
    }

    public function test(Request $request, AutoResponderService $service): RedirectResponse
    {
        $validated = $request->validate([
            'channel' => 'required|in:email,sms',
            'recipient' => 'required|string|max:255',
            'config' => 'nullable|array',
            'config.subject' => 'nullable|string|max:255',
            'config.body' => 'nullable|string',
            'config.provider' => 'nullable|string|max:64',
        ]);

        if ($validated['channel'] === 'email' && ! filter_var($validated['recipient'], FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages([
                'recipient' => 'Enter a valid email address for the test send.',
            ]);
        }

        if ($validated['channel'] === 'sms' && strlen(preg_replace('/\D/', '', $validated['recipient'])) < 7) {
            throw ValidationException::withMessages([
                'recipient' => 'Enter a valid phone number for the SMS test.',
            ]);
        }

        if (blank($validated['config']['body'] ?? null)) {
            throw ValidationException::withMessages([
                'config.body' => 'Add a message body before sending a test.',
            ]);
        }

        if ($validated['channel'] === 'email' && blank($validated['config']['subject'] ?? null)) {
            throw ValidationException::withMessages([
                'config.subject' => 'Add a subject line before sending an email test.',
            ]);
        }

        $result = $service->sendTest([
            ...$validated,
            'account_id' => AccountContext::id() ?? session('current_account_id'),
        ]);

        $flash = $result['mode'] === 'live'
            ? 'Test message sent.'
            : 'Test preview logged — messaging provider will be connected shortly.';

        return back()
            ->with('autoResponderTestResult', $result)
            ->with('success', $flash);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatedResponder($request);

        AutoResponder::create([
            ...$validated,
            'account_id' => $this->resolveAccountId($validated['campaign_id'] ?? null),
        ]);

        return back()->with('success', 'Auto responder created.');
    }

    public function update(Request $request, AutoResponder $autoResponder): RedirectResponse
    {
        $validated = $this->validatedResponder($request);

        $autoResponder->update([
            ...$validated,
            'account_id' => $this->resolveAccountId($validated['campaign_id'] ?? null, $autoResponder->account_id),
        ]);

        return back()->with('success', 'Auto responder updated.');
    }

    public function destroy(AutoResponder $autoResponder): RedirectResponse
    {
        $autoResponder->delete();

        return back()->with('success', 'Auto responder removed.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatedResponder(Request $request): array
    {
        return $request->validate([
            'campaign_id' => 'nullable|exists:campaigns,id',
            'name' => 'required|string|max:255',
            'channel' => 'required|in:email,sms',
            'trigger_event' => 'required|in:on_lead_received,on_lead_sold',
            'delay_minutes' => 'nullable|integer|min:0|max:525600',
            'status' => 'in:active,inactive',
            'config' => 'nullable|array',
            'config.subject' => 'nullable|string|max:255',
            'config.body' => 'nullable|string',
            'config.to_field' => 'nullable|string|max:64',
            'config.provider' => 'nullable|string|max:64',
        ]);
    }

    protected function resolveAccountId(?int $campaignId = null, ?int $fallbackAccountId = null): int
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
            'campaign_id' => 'Select a tenant or choose a campaign so this responder belongs to a platform.',
        ]);
    }

    /**
     * @return array{email: array{provider: string, configured: bool}, sms: array{provider: string, configured: bool}}
     */
    protected function providerStatus(): array
    {
        $emailProvider = config('messaging.email_provider', 'smtp');
        $smsProvider = config('messaging.sms_provider', 'log');

        $emailConfigured = match ($emailProvider) {
            'sendgrid' => filled(config('services.sendgrid.key')),
            'mailgun' => filled(config('services.mailgun.domain')) && filled(config('services.mailgun.secret')),
            'postmark' => filled(config('services.postmark.key')),
            'resend' => filled(config('services.resend.key')),
            default => filled(config('mail.mailers.smtp.host')) || config('mail.default') === 'log',
        };

        $smsConfigured = match ($smsProvider) {
            'twilio' => filled(config('messaging.twilio.sid'))
                && filled(config('messaging.twilio.token'))
                && filled(config('messaging.twilio.from')),
            'vonage' => filled(config('messaging.vonage.key'))
                && filled(config('messaging.vonage.secret'))
                && filled(config('messaging.vonage.from')),
            default => false,
        };

        return [
            'email' => ['provider' => $emailProvider, 'configured' => $emailConfigured],
            'sms' => ['provider' => $smsProvider, 'configured' => $smsConfigured],
        ];
    }
}
