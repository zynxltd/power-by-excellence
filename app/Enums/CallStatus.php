<?php

namespace App\Enums;

enum CallStatus: string
{
    case Ringing = 'ringing';
    case InIvr = 'in_ivr';
    case Routing = 'routing';
    case Transferring = 'transferring';
    case Connected = 'connected';
    case Completed = 'completed';
    case Unsold = 'unsold';
    case Failed = 'failed';
}
