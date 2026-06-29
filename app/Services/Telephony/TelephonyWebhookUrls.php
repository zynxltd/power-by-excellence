<?php

namespace App\Services\Telephony;

use App\Models\Account;

final class TelephonyWebhookUrls
{
    /**
     * @return array{voice_url: string, gather_url: string, status_url: string, recording_url: string}
     */
    public static function forAccount(Account $account): array
    {
        $base = rtrim((string) (config('telephony.twilio.webhook_base') ?: config('app.url')), '/');
        $slug = $account->slug;
        $paths = config('telephony.webhook_paths', []);

        return [
            'voice_url' => $base.str_replace('{accountSlug}', $slug, $paths['voice'] ?? ''),
            'gather_url' => $base.str_replace('{accountSlug}', $slug, $paths['gather'] ?? ''),
            'status_url' => $base.str_replace('{accountSlug}', $slug, $paths['status'] ?? ''),
            'recording_url' => $base.str_replace('{accountSlug}', $slug, $paths['recording'] ?? ''),
        ];
    }
}
