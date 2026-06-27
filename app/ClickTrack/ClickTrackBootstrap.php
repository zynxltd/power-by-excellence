<?php

namespace App\ClickTrack;

use App\Models\Lead;
use App\Services\ClickTrack\ClickLogService;

final class ClickTrackBootstrap
{
    public static function registerListeners(): void
    {
        if (app()->bound('click_track.lead_listener')) {
            return;
        }

        app()->instance('click_track.lead_listener', true);

        Lead::created(function (Lead $lead): void {
            $clickUuid = $lead->metadata['tracking']['click_id']
                ?? request()->input('click_id');

            if (! $clickUuid) {
                return;
            }

            app(ClickLogService::class)->attachLeadByClickUuid($lead, (string) $clickUuid);
        });
    }
}
