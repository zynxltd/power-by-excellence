<?php

namespace App\Services\Telephony;

class TelephonyManager
{
    public function gateway(?string $provider = null): TelephonyGateway
    {
        $provider = $provider ?? config('telephony.provider', 'log');

        return match ($provider) {
            'twilio' => app(TwilioVoiceGateway::class),
            default => app(LogTelephonyGateway::class),
        };
    }
}
