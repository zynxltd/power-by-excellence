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
}
