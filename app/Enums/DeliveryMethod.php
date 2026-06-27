<?php

namespace App\Enums;

enum DeliveryMethod: string
{
    case DirectPost = 'direct_post';
    case PingPost = 'ping_post';
    case TwoStepAuth = 'two_step_auth';
    case Email = 'email';
    case EmailPingPost = 'email_ping_post';
    case Sms = 'sms';
    case StoreLead = 'store_lead';
    case CampaignTransfer = 'campaign_transfer';
    case CallPingPost = 'call_ping_post';
    case CallDirectTransfer = 'call_direct_transfer';
    case CallWarmTransfer = 'call_warm_transfer';

    public function isCallMethod(): bool
    {
        return in_array($this, [
            self::CallPingPost,
            self::CallDirectTransfer,
            self::CallWarmTransfer,
        ], true);
    }
}
