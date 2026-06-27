<?php

namespace App\Services\Messaging;

use App\Models\Account;
use App\Models\Lead;
use App\Models\MessageEvent;
use App\Models\MessageSend;
use App\Models\SendingProfile;
use App\Services\Logging\PlatformLogger;

class MessageSendService
{
    public function __construct(
        protected MessagingGateway $messaging,
        protected MessagingCredentialsResolver $credentials,
        protected MarketingSuppressionService $suppression,
        protected EmailTrackingService $tracking,
        protected ThrottleGovernor $throttle,
    ) {}

    /**
     * @param  array{
     *     account_id: int,
     *     lead_id?: int|null,
     *     bulk_sms_campaign_id?: int|null,
     *     channel: string,
     *     recipient: string,
     *     subject?: string|null,
     *     body: string,
     *     html_body?: string|null,
     *     provider?: string|null,
     *     source_type?: string|null,
     *     source_id?: int|null,
     *     ab_variant?: string|null,
     *     sending_profile_id?: int|null,
     *     track?: bool,
     * }  $payload
     */
    public function send(array $payload): bool
    {
        $accountId = $payload['account_id'];
        $channel = $payload['channel'];
        $recipient = $payload['recipient'];

        if ($this->suppression->isSuppressed($accountId, $channel, $recipient)) {
            PlatformLogger::info('Marketing send suppressed', [
                'account_id' => $accountId,
                'channel' => $channel,
                'recipient' => $recipient,
            ]);

            return false;
        }

        if (! $this->throttle->allowSend($accountId)) {
            PlatformLogger::info('Marketing send throttled', ['account_id' => $accountId]);

            return false;
        }

        $account = Account::find($accountId);
        $profile = $this->credentials->resolveSendingProfile(
            $accountId,
            $payload['sending_profile_id'] ?? null,
            $channel === 'email' ? $recipient : null,
        );

        $resolved = $this->credentials->resolveForAccount(
            $account,
            $payload['provider'] ?? $profile?->provider,
            $profile,
        );

        $send = MessageSend::create([
            'account_id' => $accountId,
            'lead_id' => $payload['lead_id'] ?? null,
            'bulk_sms_campaign_id' => $payload['bulk_sms_campaign_id'] ?? null,
            'channel' => $channel,
            'provider' => $resolved['provider'],
            'source_type' => $payload['source_type'] ?? null,
            'source_id' => $payload['source_id'] ?? null,
            'recipient' => $recipient,
            'subject' => $payload['subject'] ?? null,
            'body' => $payload['body'],
            'ab_variant' => $payload['ab_variant'] ?? null,
            'status' => 'pending',
        ]);

        $options = [
            'provider' => $resolved['provider'],
            'from' => $resolved['from'],
            'reply_to' => $resolved['reply_to'],
            'credentials' => $resolved['credentials'],
        ];

        $ok = false;

        if ($channel === 'email') {
            $htmlBody = $payload['html_body'] ?? null;
            $track = $payload['track'] ?? true;

            if ($track && ($htmlBody || filled($payload['body']))) {
                $html = $this->tracking->buildHtmlEmail($payload['body'], $htmlBody, $send);
                $options['html'] = $html;
            }

            $ok = $this->messaging->sendEmail(
                $recipient,
                $payload['subject'] ?? 'Message',
                $payload['body'],
                $options,
            );
        } else {
            $ok = $this->messaging->sendSms($recipient, $payload['body'], $options);
        }

        $send->update([
            'status' => $ok ? 'sent' : 'failed',
            'sent_at' => $ok ? now() : null,
        ]);

        if ($ok) {
            MessageEvent::create([
                'message_send_id' => $send->id,
                'account_id' => $accountId,
                'type' => 'sent',
                'occurred_at' => now(),
            ]);
        }

        return $ok;
    }

    public function recordEvent(MessageSend $send, string $type, ?string $url = null, ?array $meta = null): void
    {
        MessageEvent::create([
            'message_send_id' => $send->id,
            'account_id' => $send->account_id,
            'type' => $type,
            'url' => $url,
            'meta' => $meta,
            'occurred_at' => now(),
        ]);

        if (in_array($type, ['bounce', 'complaint'], true)) {
            $send->update(['status' => $type]);
        } elseif ($type === 'delivered') {
            $send->update(['status' => 'delivered']);
        }
    }
}
