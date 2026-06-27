<?php

namespace App\Jobs;

use App\Models\BulkSmsCampaign;
use App\Services\Messaging\BulkCampaignSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendBulkCampaignJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $campaignId,
    ) {}

    public function handle(BulkCampaignSender $sender): void
    {
        $campaign = BulkSmsCampaign::withoutGlobalScopes()->find($this->campaignId);

        if ($campaign && in_array($campaign->status, ['draft', 'scheduled', 'sending'], true)) {
            $sender->send($campaign);
        }
    }
}
