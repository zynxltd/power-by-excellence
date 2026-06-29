<?php

namespace App\Enums;

enum DeliveryMethod: string
{
    case DirectPost = 'direct_post';
    case PingPost = 'ping_post';
    case PingOnly = 'ping_only';
    case TwoStepAuth = 'two_step_auth';
    case Email = 'email';
    case EmailPingPost = 'email_ping_post';
    case Sms = 'sms';
    case StoreLead = 'store_lead';
    case CampaignTransfer = 'campaign_transfer';
    case CallPingPost = 'call_ping_post';
    case CallDirectTransfer = 'call_direct_transfer';
    case CallWarmTransfer = 'call_warm_transfer';

    /**
     * @return list<self>
     */
    public static function httpLeadMethods(): array
    {
        return [
            self::DirectPost,
            self::PingPost,
            self::PingOnly,
            self::TwoStepAuth,
            self::Email,
            self::EmailPingPost,
            self::Sms,
            self::StoreLead,
            self::CampaignTransfer,
        ];
    }

    /**
     * @return list<self>
     */
    public static function callMethods(): array
    {
        return [
            self::CallPingPost,
            self::CallDirectTransfer,
            self::CallWarmTransfer,
        ];
    }

    public function isCallMethod(): bool
    {
        return in_array($this, self::callMethods(), true);
    }

    public function usesHttpPing(): bool
    {
        return in_array($this, [
            self::PingPost,
            self::PingOnly,
            self::TwoStepAuth,
            self::CallPingPost,
            self::CallWarmTransfer,
        ], true);
    }

    public function isPingOnly(): bool
    {
        return $this === self::PingOnly;
    }

    public function label(): string
    {
        return match ($this) {
            self::DirectPost => 'Direct Post',
            self::PingPost => 'Ping Post',
            self::PingOnly => 'Ping Only',
            self::TwoStepAuth => '2-Step Auth',
            self::Email => 'Email',
            self::EmailPingPost => 'Email Ping-Post',
            self::Sms => 'SMS',
            self::StoreLead => 'Store Lead',
            self::CampaignTransfer => 'Campaign Transfer',
            self::CallPingPost => 'Call Ping-Post',
            self::CallDirectTransfer => 'Call Direct Transfer',
            self::CallWarmTransfer => 'Call Warm Transfer',
        };
    }
}
