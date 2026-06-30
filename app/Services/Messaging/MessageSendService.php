<?php

namespace App\Services\Messaging;

use App\Models\Account;
use App\Models\Lead;
use App\Models\MessageEvent;
use App\Models\MessageSend;
use App\Services\Logging\PlatformLogger;

class MessageSendService
{
    public function __construct(
        protected MessagingGateway $messaging,
        protected MessagingCredentialsResolver $credentials,
        protected MarketingSuppressionService $suppression,
        protected EmailTrackingService $tracking,
        protected ThrottleGovernor $throttle,
        protected TemplateRenderService $templateRender,
        protected SendTimeOptimizer $sendTimeOptimizer,
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
     *     skip_send_time_optimization?: bool,
     *     existing_send_id?: int|null,
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

        $account = Account::find($accountId);
        if (! $account) {
            return false;
        }

        $profile = $this->credentials->resolveSendingProfile(
            $accountId,
            $payload['sending_profile_id'] ?? null,
            $channel === 'email' ? $recipient : null,
        );

        if ($profile && $this->throttle instanceof WarmupGovernor) {
            $this->throttle->ensureWarmupStarted($profile);
        }

        if (! $this->throttle->allowSend($accountId, $profile)) {
            PlatformLogger::info('Marketing send throttled', [
                'account_id' => $accountId,
                'sending_profile_id' => $profile?->id,
            ]);

            return false;
        }

        $subject = $payload['subject'] ?? null;
        $body = $payload['body'];
        $htmlBody = $payload['html_body'] ?? null;
        $lead = null;

        if (! empty($payload['lead_id'])) {
            $lead = Lead::find($payload['lead_id']);
            if ($lead) {
                $rendered = $this->templateRender->renderParts([
                    'subject' => $subject,
                    'body' => $body,
                    'html_body' => $htmlBody,
                ], $lead);
                $subject = $rendered['subject'] ?? $subject;
                $body = $rendered['body'] ?? $body;
                $htmlBody = $rendered['html_body'] ?? $htmlBody;
            }
        }

        if (empty($payload['skip_send_time_optimization']) && empty($payload['existing_send_id'])) {
            $scheduledAt = $this->sendTimeOptimizer->computeSendAt($account, $lead);

            if ($scheduledAt && $scheduledAt->isFuture()) {
                MessageSend::create([
                    'account_id' => $accountId,
                    'lead_id' => $payload['lead_id'] ?? null,
                    'bulk_sms_campaign_id' => $payload['bulk_sms_campaign_id'] ?? null,
                    'sending_profile_id' => $profile?->id,
                    'channel' => $channel,
                    'provider' => $payload['provider'] ?? $profile?->provider,
                    'source_type' => $payload['source_type'] ?? null,
                    'source_id' => $payload['source_id'] ?? null,
                    'recipient' => $recipient,
                    'subject' => $subject,
                    'body' => $body,
                    'ab_variant' => $payload['ab_variant'] ?? null,
                    'status' => 'scheduled',
                    'scheduled_at' => $scheduledAt,
                ]);

                PlatformLogger::info('Marketing send queued for send-time optimization', [
                    'account_id' => $accountId,
                    'lead_id' => $payload['lead_id'] ?? null,
                    'scheduled_at' => $scheduledAt->toIso8601String(),
                ]);

                return true;
            }
        }

        $resolved = $this->credentials->resolveForAccount(
            $account,
            $payload['provider'] ?? $profile?->provider,
            $profile,
        );

        if (! empty($payload['existing_send_id'])) {
            $send = MessageSend::withoutGlobalScopes()->find($payload['existing_send_id']);
            if (! $send) {
                return false;
            }

            $send->update([
                'sending_profile_id' => $profile?->id,
                'provider' => $resolved['provider'],
                'subject' => $subject,
                'body' => $body,
                'status' => 'pending',
                'scheduled_at' => null,
            ]);
        } else {
            $send = MessageSend::create([
                'account_id' => $accountId,
                'lead_id' => $payload['lead_id'] ?? null,
                'bulk_sms_campaign_id' => $payload['bulk_sms_campaign_id'] ?? null,
                'sending_profile_id' => $profile?->id,
                'channel' => $channel,
                'provider' => $resolved['provider'],
                'source_type' => $payload['source_type'] ?? null,
                'source_id' => $payload['source_id'] ?? null,
                'recipient' => $recipient,
                'subject' => $subject,
                'body' => $body,
                'ab_variant' => $payload['ab_variant'] ?? null,
                'status' => 'pending',
            ]);
        }

        $options = [
            'provider' => $resolved['provider'],
            'from' => $resolved['from'],
            'reply_to' => $resolved['reply_to'],
            'credentials' => $resolved['credentials'],
        ];

        $ok = false;

        if ($channel === 'email') {
            $track = $payload['track'] ?? true;

            if ($track && ($htmlBody || filled($body))) {
                $html = $this->tracking->buildHtmlEmail($body, $htmlBody, $send);
                $options['html'] = $html;
            }

            $ok = $this->messaging->sendEmail(
                $recipient,
                $subject ?? 'Message',
                $body,
                $options,
            );
        } else {
            $ok = $this->messaging->sendSms($recipient, $body, $options);
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

    public function processScheduled(MessageSend $send): bool
    {
        if ($send->status !== 'scheduled' || ! $send->scheduled_at || $send->scheduled_at->isFuture()) {
            return false;
        }

        return $this->send([
            'account_id' => $send->account_id,
            'lead_id' => $send->lead_id,
            'bulk_sms_campaign_id' => $send->bulk_sms_campaign_id,
            'channel' => $send->channel,
            'recipient' => $send->recipient,
            'subject' => $send->subject,
            'body' => $send->body ?? '',
            'provider' => $send->provider,
            'source_type' => $send->source_type,
            'source_id' => $send->source_id,
            'sending_profile_id' => $send->sending_profile_id,
            'ab_variant' => $send->ab_variant,
            'track' => $send->channel === 'email',
            'skip_send_time_optimization' => true,
            'existing_send_id' => $send->id,
        ]);
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
