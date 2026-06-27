<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\CallEventType;
use App\Enums\CallStatus;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\CallSession;
use App\Models\TrackingNumber;
use App\Services\Calls\CallEventLogger;
use App\Services\Calls\CallRecordingService;
use App\Services\Calls\CallRouter;
use App\Services\Calls\IvrEngine;
use App\Services\Telephony\TelephonyManager;
use App\Support\Products\CallLogicProduct;
use App\Support\Tenancy\AccountContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class TwilioVoiceWebhookController extends Controller
{
    public function inbound(
        Request $request,
        string $accountSlug,
        CallRouter $router,
        CallEventLogger $logger,
        IvrEngine $ivrEngine,
        TelephonyManager $telephony,
    ): Response {
        $account = Account::where('slug', $accountSlug)->firstOrFail();

        if (! CallLogicProduct::isEnabled($account)) {
            return response('<Response><Say>Service unavailable.</Say></Response>', 403)
                ->header('Content-Type', 'text/xml');
        }

        AccountContext::set($account);

        $to = $request->input('To') ?? $request->input('Called');
        $from = $request->input('From') ?? $request->input('Caller');

        $tracking = TrackingNumber::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->where('phone_number', $to)
            ->where('status', 'active')
            ->first();

        if (! $tracking) {
            return response('<Response><Say>Invalid number.</Say></Response>', 404)
                ->header('Content-Type', 'text/xml');
        }

        $ivrFlow = $ivrEngine->resolveFlowForCampaign($tracking->campaign_id);
        $campaign = $tracking->campaign;

        $session = CallSession::create([
            'account_id' => $account->id,
            'campaign_id' => $tracking->campaign_id,
            'tracking_number_id' => $tracking->id,
            'ivr_flow_id' => $ivrFlow?->id,
            'status' => CallStatus::Ringing,
            'caller_number' => $from,
            'provider_call_sid' => $request->input('CallSid'),
            'sid' => $request->input('sid'),
            'ssid' => $request->input('ssid'),
            'min_duration_seconds' => $campaign?->call_settings['min_duration_seconds']
                ?? config('telephony.default_min_duration_seconds', 60),
            'metadata' => ['ivr_current_node' => $ivrFlow?->entry_node ?? 'start'],
        ]);

        $logger->log($session, CallEventType::Inbound, 'Inbound call received', [
            'from' => $from,
            'to' => $to,
        ]);

        if ($ivrFlow) {
            $session->update(['status' => CallStatus::InIvr]);
        }

        $webhookBase = url('/webhooks/twilio/voice/'.$accountSlug);
        $twiml = $router->buildTwiml($session->fresh(), $webhookBase);

        return response($twiml, 200)->header('Content-Type', 'text/xml');
    }

    public function gather(
        Request $request,
        string $accountSlug,
        CallRouter $router,
        IvrEngine $ivrEngine,
    ): Response {
        $account = Account::where('slug', $accountSlug)->firstOrFail();
        AccountContext::set($account);

        $session = CallSession::where('uuid', $request->query('session'))->firstOrFail();
        $digits = $request->input('Digits');

        $ivrEngine->logStep($session, $session->metadata['ivr_current_node'] ?? 'start', $digits);

        $session->update([
            'ivr_data' => array_merge($session->ivr_data ?? [], ['last_digits' => $digits]),
        ]);

        $webhookBase = url('/webhooks/twilio/voice/'.$accountSlug);
        $twiml = $router->buildTwiml($session->fresh(), $webhookBase);

        return response($twiml, 200)->header('Content-Type', 'text/xml');
    }

    public function status(Request $request, string $accountSlug, CallRouter $router): Response
    {
        $account = Account::where('slug', $accountSlug)->firstOrFail();
        AccountContext::set($account);

        $callSid = $request->input('CallSid');
        $session = CallSession::where('provider_call_sid', $callSid)->first();

        if (! $session) {
            return response('', 204);
        }

        $callStatus = $request->input('CallStatus');
        $duration = (int) $request->input('CallDuration', 0);

        if (in_array($callStatus, ['completed', 'busy', 'no-answer', 'failed', 'canceled'], true)) {
            $router->recordDisposition($session, [
                'disposition' => Str::snake($callStatus ?? 'completed'),
                'duration_seconds' => $duration,
            ]);
        }

        return response('', 204);
    }

    public function recording(
        Request $request,
        string $accountSlug,
        CallRecordingService $recordings,
        CallEventLogger $logger,
    ): Response {
        $account = Account::where('slug', $accountSlug)->firstOrFail();
        AccountContext::set($account);

        $callSid = $request->input('CallSid');
        $session = CallSession::where('provider_call_sid', $callSid)->first();

        if (! $session) {
            return response('', 204);
        }

        $recording = $recordings->attachFromWebhook($session, $request->all());
        $logger->log($session, CallEventType::Recording, 'Recording callback received', [
            'recording_sid' => $recording->provider_recording_sid,
            'status' => $recording->status,
        ]);

        return response('', 204);
    }
}
