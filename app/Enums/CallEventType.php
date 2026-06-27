<?php

namespace App\Enums;

enum CallEventType: string
{
    case Inbound = 'inbound';
    case IvrStep = 'ivr_step';
    case PingSent = 'ping_sent';
    case PingAccepted = 'ping_accepted';
    case PingRejected = 'ping_rejected';
    case Transfer = 'transfer';
    case Connected = 'connected';
    case Disposition = 'disposition';
    case Recording = 'recording';
    case HybridFallback = 'hybrid_fallback';
    case Completed = 'completed';
    case Failed = 'failed';
}
