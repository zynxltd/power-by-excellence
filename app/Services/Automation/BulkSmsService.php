<?php

namespace App\Services\Automation;

use App\Models\BulkSmsCampaign;
use App\Services\Messaging\BulkCampaignSender;

class BulkSmsService
{
    public function __construct(
        protected BulkCampaignSender $sender,
    ) {}

    public function send(BulkSmsCampaign $campaign): BulkSmsCampaign
    {
        return $this->sender->send($campaign);
    }
}
