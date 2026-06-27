<?php

namespace App\Services\Calls;

use App\Enums\CallEventType;
use App\Enums\CallStatus;
use App\Models\CallEvent;
use App\Models\CallSession;

class CallEventLogger
{
    public function log(
        CallSession $session,
        CallEventType $type,
        string $message,
        array $payload = [],
        string $level = 'info',
    ): CallEvent {
        return CallEvent::create([
            'call_session_id' => $session->id,
            'event_type' => $type,
            'level' => $level,
            'message' => $message,
            'payload' => $payload,
        ]);
    }
}
