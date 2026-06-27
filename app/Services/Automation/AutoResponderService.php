<?php

namespace App\Services\Automation;

use App\Jobs\RunAutoResponderJob;
use App\Models\AutoResponder;
use App\Models\Lead;
use App\Services\Delivery\TagInterpolator;
use App\Services\Logging\PlatformLogger;
use App\Services\Messaging\MessagingGateway;

class AutoResponderService
{
    public function __construct(
        protected TagInterpolator $interpolator,
        protected MessagingGateway $messaging,
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
            $effectiveProvider = $provider ?? config('messaging.email_provider', 'smtp');
            $live = $this->isEmailProviderLive($effectiveProvider);

            $sent = $this->messaging->sendEmail($recipient, $subject, $body, [
                'provider' => $provider,
            ]);

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
        $effectiveProvider = $provider ?? config('messaging.sms_provider', 'log');
        $live = $this->isSmsProviderLive($effectiveProvider);

        $sent = $this->messaging->sendSms($recipient, $message, [
            'provider' => $live ? $provider : 'log',
        ]);

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

                $this->messaging->sendEmail($to, $subject, $body, [
                    'provider' => $provider,
                ]);
            } elseif ($responder->channel === 'sms') {
                $toField = $config['to_field'] ?? 'phone1';
                $to = $fields[$toField] ?? null;
                if (! $to) {
                    return;
                }

                $message = $this->interpolator->interpolate($config['body'] ?? $config['message'] ?? '', $fields);
                $this->messaging->sendSms($to, $message, [
                    'provider' => $provider,
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

    protected function isEmailProviderLive(string $provider): bool
    {
        return match ($provider) {
            'sendgrid' => filled(config('services.sendgrid.key')),
            'mailgun' => filled(config('services.mailgun.domain')) && filled(config('services.mailgun.secret')),
            'postmark' => filled(config('services.postmark.key')),
            'resend' => filled(config('services.resend.key')),
            default => filled(config('mail.mailers.smtp.host')) || config('mail.default') === 'log',
        };
    }

    protected function isSmsProviderLive(string $provider): bool
    {
        return match ($provider) {
            'twilio' => filled(config('messaging.twilio.sid'))
                && filled(config('messaging.twilio.token'))
                && filled(config('messaging.twilio.from')),
            'vonage' => filled(config('messaging.vonage.key'))
                && filled(config('messaging.vonage.secret'))
                && filled(config('messaging.vonage.from')),
            default => false,
        };
    }
}
