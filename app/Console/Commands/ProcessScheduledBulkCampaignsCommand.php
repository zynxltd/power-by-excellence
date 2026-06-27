<?php

namespace App\Console\Commands;

use App\Jobs\SendBulkCampaignJob;
use App\Models\BulkSmsCampaign;
use Illuminate\Console\Command;

class ProcessScheduledBulkCampaignsCommand extends Command
{
    protected $signature = 'bulk:process-scheduled';

    protected $description = 'Dispatch bulk email/SMS campaigns that are due to send';

    public function handle(): int
    {
        $campaigns = BulkSmsCampaign::withoutGlobalScopes()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        foreach ($campaigns as $campaign) {
            $campaign->update(['status' => 'queued']);
            SendBulkCampaignJob::dispatch($campaign->id);
        }

        $this->info("Dispatched {$campaigns->count()} scheduled bulk campaign(s).");

        return self::SUCCESS;
    }
}
