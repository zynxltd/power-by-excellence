<?php

namespace App\Services\ClickTrack;

use App\Models\TrackingLink;
use App\Services\Alerts\EventAlertService;

class ClickTrackCapAlertService
{
    public function __construct(
        protected ClickCapService $caps,
        protected EventAlertService $alerts,
    ) {}

    public function evaluateAfterClick(TrackingLink $link): void
    {
        $usage = $this->caps->usageForLink($link);

        if ($usage['click_soft_cap_reached'] || $usage['click_cap_reached']) {
            $this->alerts->evaluateForAccount($link->account_id);
        }
    }

    public function evaluateForAccount(int $accountId): void
    {
        $this->alerts->evaluateForAccount($accountId);
    }
}
