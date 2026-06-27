<?php

namespace App\Jobs;

use App\Models\BulkSmsCampaign;
use App\Services\Messaging\AbTestService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EvaluateAbTestWinnerJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $campaignId,
    ) {}

    public function handle(AbTestService $abTest): void
    {
        $campaign = BulkSmsCampaign::withoutGlobalScopes()->find($this->campaignId);

        if ($campaign) {
            $abTest->evaluateAndSendWinner($campaign);
        }
    }
}
