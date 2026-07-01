<?php

namespace App\Services\Telephony;

use App\Models\CallSession;

class CallTwimlContext
{
    public function __construct(
        public CallSession $session,
        public string $actionUrl,
        public ?string $message = null,
        public ?string $gatherUrl = null,
        public ?string $transferNumber = null,
        public bool $record = false,
        public ?string $whisperUrl = null,
        public bool $hangup = false,
    ) {}
}
