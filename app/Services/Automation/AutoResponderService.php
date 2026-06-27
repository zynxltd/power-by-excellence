<?php

namespace App\Services\Automation;

use App\Jobs\RunAutoResponderJob;
use App\Models\AutoResponder;
use App\Models\Lead;
use App\Services\Delivery\TagInterpolator;
use App\Services\Logging\PlatformLogger;
use App\Services\Messaging\MessageSendService;
use App\Services\Messaging\MessagingCredentialsResolver;

class AutoResponderService
{
    public function __construct(
        protected TagInterpolator $interpolator,
        protected MessageSendService $sender,
        protected MessagingCredentialsResolver $credentials,
    ) {}

    public function dispatchForLead(Lead $lead, string $triggerEvent): void
    {
        $responders = AutoResponder::query()
            ->where('account_id', $lead->account_id)
            ->where('status', 'active')
            ->where('trigger_event', $triggerEvent)
            ->where(function ($q) use ($lead) {
                $q->whereNull('campaign_id')->orWhere('campaign_id', $lead->campaign_id);
            })
            ->get();

        foreach ($responders as $responder) {
            $delay = (int) ($responder->delay_minutes ?? 0);

            if ($delay > 0) {
                RunAutoResponderJob::dispatch($lead->id, $responder->id)
                    ->delay(now()->addMinutes($delay));
            } else {
                $this->sendForLead($responder, $lead);
            }
        }
    }

    /**
     * @param  array{channel: string, recipient: string, config?: array<string, mixed>}  $payload
     * @return array{success: bool, channel: string, recipient: string, subject?: string, body: string, mode: string, notice?: string}
     */
    public function sendTest(array $payload): array
    {
        $channel = $payload['channel'];
        $config = $payload['config'] ?? [];
        $recipient = $payload['recipient'];
        $provider = filled($config['provider'] ?? null) ? $config['provider'] : null;
        $accountId = (int) ($payload['account_id'] ?? 0);

        $fields = [
            'firstname' => 'Alex',
            'lastname' => 'Morgan',
            'email' => $channel === 'email' ? $recipient : 'alex@example.com',
            'phone1' => $channel === 'sms' ? $recipient : '555-0142',
            'zipcode' => 'M5H 2N2',
        ];

        if ($channel === 'email') {
            $subject = $this->interpolator->interpolate($config['subject'] ?? 'Test message', $fields);
            $body = $this->interpolator->interpolate($config['body'] ?? '', $fields);
            $account = $accountId ? \App\Models\Account::find($accountId) : null;
            $effectiveProvider = $provider ?? config('messaging.email_provider', 'smtp');
            $live = $this->credentials->isProviderLive($account, $effectiveProvider, 'email');

            $sent = $accountId ? $this->sender->send([
                'account_id' => $accountId,
                'channel' => 'email',
                'recipient' => $recipient,
                'subject' => $subject,
                'body' => $body,
                'provider' => $provider,
                'source_type' => 'auto_responder_test',
                'track' => false,
            ]) : false;

            return [
                'success' => $sent,
                'channel' => 'email',
                'recipient' => $recipient,
                'subject' => $subject,
                'body' => $body,
                'mode' => $live ? 'live' : 'preview',
                'notice' => $live
                    ? null
                    : 'Email provider credentials are not configured yet. Merge tags were rendered below; live delivery will be enabled when your email provider is connected.',
            ];
        }

        $message = $this->interpolator->interpolate($config['body'] ?? $config['message'] ?? '', $fields);
        $account = $accountId ? \App\Models\Account::find($accountId) : null;
        $effectiveProvider = $provider ?? config('messaging.sms_provider', 'log');
        $live = $this->credentials->isProviderLive($account, $effectiveProvider, 'sms');

        $sent = $accountId ? $this->sender->send([
            'account_id' => $accountId,
            'channel' => 'sms',
            'recipient' => $recipient,
            'body' => $message,
            'provider' => $live ? $provider : 'log',
            'source_type' => 'auto_responder_test',
            'track' => false,
        ]) : false;

        return [
            'success' => $sent,
            'channel' => 'sms',
            'recipient' => $recipient,
            'body' => $message,
            'mode' => $live ? 'live' : 'preview',
            'notice' => $live
                ? null
                : 'SMS provider credentials are not configured yet. The message was logged to the platform log; live delivery will be enabled when your SMS provider is connected.',
        ];
    }

    public function sendForLead(AutoResponder $responder, Lead $lead): void
    {
        $fields = $lead->fresh()->allFields();
        $config = $responder->config ?? [];
        $provider = $config['provider'] ?? null;

        try {
            if ($responder->channel === 'email') {
                $toField = $config['to_field'] ?? 'email';
                $to = $fields[$toField] ?? null;
                if (! $to) {
                    return;
                }

                $subject = $this->interpolator->interpolate($config['subject'] ?? 'Thank you', $fields);
                $body = $this->interpolator->interpolate($config['body'] ?? '', $fields);
                $htmlBody = filled($config['html_body'] ?? null)
                    ? $this->interpolator->interpolate($config['html_body'], $fields)
                    : null;

                $this->sender->send([
                    'account_id' => $lead->account_id,
                    'lead_id' => $lead->id,
                    'channel' => 'email',
                    'recipient' => $to,
                    'subject' => $subject,
                    'body' => $body,
                    'html_body' => $htmlBody,
                    'provider' => $provider,
                    'source_type' => AutoResponder::class,
                    'source_id' => $responder->id,
                ]);
            } elseif ($responder->channel === 'sms') {
                $toField = $config['to_field'] ?? 'phone1';
                $to = $fields[$toField] ?? null;
                if (! $to) {
                    return;
                }

                $message = $this->interpolator->interpolate($config['body'] ?? $config['message'] ?? '', $fields);
                $this->sender->send([
                    'account_id' => $lead->account_id,
                    'lead_id' => $lead->id,
                    'channel' => 'sms',
                    'recipient' => $to,
                    'body' => $message,
                    'provider' => $provider,
                    'source_type' => AutoResponder::class,
                    'source_id' => $responder->id,
                    'track' => false,
                ]);
            }

            PlatformLogger::leadEvent($lead, 'auto_responder.sent', "Auto responder: {$responder->name}", [
                'responder_id' => $responder->id,
                'channel' => $responder->channel,
                'provider' => $provider,
                'delay_minutes' => $responder->delay_minutes ?? 0,
            ]);
        } catch (\Throwable $e) {
            PlatformLogger::error('Auto responder failed', [
                'responder_id' => $responder->id,
                'lead_id' => $lead->id,
            ], $lead, $e);
        }
    }
}

