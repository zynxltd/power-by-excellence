<?php

namespace App\Services\ClickTrack;

use App\Models\Account;
use App\Models\TrackingClick;
use App\Models\TrackingLink;
use Illuminate\Http\Request;

class ClickFraudService
{
    public function isEnabled(?Account $account): bool
    {
        if (! $account) {
            return (bool) config('click_track.fraud.block_duplicates', true);
        }

        $settings = $account->settings['click_track'] ?? [];

        return (bool) ($settings['fraud_block_duplicates'] ?? config('click_track.fraud.block_duplicates', true));
    }

    public function duplicateWindowMinutes(?Account $account): int
    {
        $settings = $account?->settings['click_track'] ?? [];

        return (int) ($settings['fraud_duplicate_window_minutes']
            ?? config('click_track.fraud.duplicate_window_minutes', 60));
    }

    /**
     * Same IP + sub1 within the fraud window counts as a duplicate click.
     */
    public function findDuplicateClick(TrackingLink $link, Request $request, array $subs): ?TrackingClick
    {
        if (! $this->isEnabled($link->account)) {
            return null;
        }

        $sub1 = $subs['sub1'] ?? null;
        $ip = $request->ip();

        if (! $ip || ! $sub1) {
            return null;
        }

        return TrackingClick::withoutGlobalScopes()
            ->where('tracking_link_id', $link->id)
            ->where('ip_address', $ip)
            ->where('sub1', $sub1)
            ->where('clicked_at', '>=', now()->subMinutes($this->duplicateWindowMinutes($link->account)))
            ->orderByDesc('clicked_at')
            ->first();
    }
}
