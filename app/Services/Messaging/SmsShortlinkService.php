<?php

namespace App\Services\Messaging;

use App\Models\Account;
use App\Models\MessageEvent;
use App\Models\MessageSend;
use App\Models\MessageShortLink;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SmsShortlinkService
{
    public function isEnabled(?Account $account): bool
    {
        if (! $account) {
            return false;
        }

        return (bool) ($account->settings['messaging']['sms_shortlinks_enabled'] ?? false);
    }

    /**
     * @return array{sms_shortlinks_enabled: bool}
     */
    public function settings(Account $account): array
    {
        return [
            'sms_shortlinks_enabled' => $this->isEnabled($account),
        ];
    }

    /**
     * @param  array{sms_shortlinks_enabled?: bool}  $validated
     * @return array<string, mixed>
     */
    public function mergeSettingsIntoAccount(Account $account, array $validated): array
    {
        $settings = $account->settings ?? [];
        $messaging = $settings['messaging'] ?? [];
        $messaging['sms_shortlinks_enabled'] = (bool) ($validated['sms_shortlinks_enabled'] ?? false);
        $settings['messaging'] = $messaging;

        return $settings;
    }

    public function rewriteSmsBody(
        Account $account,
        string $body,
        MessageSend $send,
        ?int $automationSequenceStepId = null,
    ): string {
        if (! $this->isEnabled($account)) {
            return $body;
        }

        $created = [];

        return preg_replace_callback(
            '/https?:\/\/[^\s<>"\'\]]+/i',
            function (array $matches) use ($account, $send, $automationSequenceStepId, &$created) {
                $destination = $this->normalizeUrl($matches[0]);

                if (! filter_var($destination, FILTER_VALIDATE_URL)) {
                    return $matches[0];
                }

                $cacheKey = strtolower($destination);

                if (isset($created[$cacheKey])) {
                    return $created[$cacheKey];
                }

                $link = $this->findExistingLink($send, $destination)
                    ?? $this->createLink($account, $send, $destination, $automationSequenceStepId);

                $shortUrl = url('/s/'.$link->slug);
                $created[$cacheKey] = $shortUrl;

                return $shortUrl;
            },
            $body,
        ) ?? $body;
    }

    public function recordClick(MessageShortLink $link): void
    {
        $link->increment('click_count');

        $send = $link->messageSend;

        if (! $send) {
            return;
        }

        app(MessageSendService::class)->recordEvent(
            $send,
            'click',
            $link->destination_url,
            [
                'shortlink_id' => $link->id,
                'via' => 'sms_shortlink',
            ],
        );
    }

    /**
     * @return array{
     *     clicks_30d: int,
     *     links_30d: int,
     *     recent_sends: Collection<int, array<string, mixed>>,
     * }
     */
    public function stats(int $accountId, int $days = 30): array
    {
        $since = now()->subDays($days);

        $clicks30d = MessageEvent::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('type', 'click')
            ->where('occurred_at', '>=', $since)
            ->where('meta->via', 'sms_shortlink')
            ->count();

        $links30d = MessageShortLink::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('created_at', '>=', $since)
            ->count();

        $recentSends = MessageSend::withoutGlobalScopes()
            ->where('account_id', $accountId)
            ->where('channel', 'sms')
            ->where('created_at', '>=', $since)
            ->withCount([
                'shortLinks',
                'events as shortlink_clicks_count' => fn ($query) => $query
                    ->where('type', 'click')
                    ->where('meta->via', 'sms_shortlink'),
            ])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->map(fn (MessageSend $send) => [
                'id' => $send->id,
                'recipient' => $send->recipient,
                'body_preview' => Str::limit($send->body ?? '', 80),
                'sent_at' => $send->sent_at?->toIso8601String(),
                'short_links' => $send->short_links_count,
                'clicks' => $send->shortlink_clicks_count,
            ])
            ->values();

        return [
            'clicks_30d' => $clicks30d,
            'links_30d' => $links30d,
            'recent_sends' => $recentSends,
        ];
    }

    protected function findExistingLink(MessageSend $send, string $destination): ?MessageShortLink
    {
        return MessageShortLink::withoutGlobalScopes()
            ->where('message_send_id', $send->id)
            ->where('destination_url', $destination)
            ->first();
    }

    protected function createLink(
        Account $account,
        MessageSend $send,
        string $destination,
        ?int $automationSequenceStepId,
    ): MessageShortLink {
        do {
            $slug = Str::lower(Str::random(8));
        } while (MessageShortLink::withoutGlobalScopes()->where('slug', $slug)->exists());

        return MessageShortLink::create([
            'account_id' => $account->id,
            'message_send_id' => $send->id,
            'automation_sequence_step_id' => $automationSequenceStepId,
            'slug' => $slug,
            'destination_url' => $destination,
        ]);
    }

    protected function normalizeUrl(string $url): string
    {
        return rtrim($url, '.,);]');
    }
}
