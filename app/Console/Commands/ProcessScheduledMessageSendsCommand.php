<?php

namespace App\Console\Commands;

use App\Models\MessageSend;
use App\Services\Messaging\MessageSendService;
use Illuminate\Console\Command;

class ProcessScheduledMessageSendsCommand extends Command
{
    protected $signature = 'messaging:process-scheduled';

    protected $description = 'Dispatch individual message sends deferred for quiet hours or send-time optimization';

    public function handle(MessageSendService $sender): int
    {
        $processed = 0;

        MessageSend::withoutGlobalScopes()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit(500)
            ->get()
            ->each(function (MessageSend $send) use ($sender, &$processed) {
                if ($sender->processScheduled($send)) {
                    $processed++;
                }
            });

        $this->info("Processed {$processed} scheduled message send(s).");

        return self::SUCCESS;
    }
}
