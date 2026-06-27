<?php

namespace App\Services\Telephony;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LogTelephonyGateway implements TelephonyGateway
{
    public function provider(): string
    {
        return 'log';
    }

    public function provisionNumber(string $areaCode = '020'): array
    {
        $sid = 'LOG'.Str::upper(Str::random(10));
        $number = '+44'.$areaCode.Str::padLeft((string) random_int(1000000, 9999999), 7, '0');

        Log::info('telephony.provision', ['sid' => $sid, 'number' => $number]);

        return ['sid' => $sid, 'phone_number' => $number];
    }

    public function releaseNumber(string $providerSid): void
    {
        Log::info('telephony.release', ['sid' => $providerSid]);
    }

    public function buildInboundTwiml(CallTwimlContext $context): string
    {
        $statusAttr = $context->actionUrl
            ? ' statusCallback="'.htmlspecialchars($context->actionUrl).'" statusCallbackMethod="POST"'
            : '';

        $lines = ['<?xml version="1.0" encoding="UTF-8"?>', '<Response'.$statusAttr.'>'];

        if ($context->message && ! $context->gatherUrl) {
            $lines[] = '<Say>'.htmlspecialchars($context->message).'</Say>';
        }

        if ($context->gatherUrl) {
            $prompt = $context->message ?? 'Please enter your selection.';
            $lines[] = '<Gather action="'.htmlspecialchars($context->gatherUrl).'" numDigits="1">';
            $lines[] = '<Say>'.htmlspecialchars($prompt).'</Say>';
            $lines[] = '</Gather>';
        }

        if ($context->transferNumber) {
            if ($context->record) {
                $lines[] = '<Dial record="record-from-answer">'.htmlspecialchars($context->transferNumber).'</Dial>';
            } else {
                $lines[] = '<Dial>'.htmlspecialchars($context->transferNumber).'</Dial>';
            }
        } elseif (! $context->gatherUrl) {
            $lines[] = '<Say>Thank you for calling. Goodbye.</Say>';
        }

        $lines[] = '</Response>';

        return implode("\n", $lines);
    }

    public function transferCall(string $providerCallSid, string $destination, array $options = []): array
    {
        Log::info('telephony.transfer', [
            'call_sid' => $providerCallSid,
            'destination' => $destination,
            'options' => $options,
        ]);

        return ['status' => 'initiated', 'destination' => $destination];
    }

    public function startRecording(string $providerCallSid): ?string
    {
        $sid = 'REC'.Str::upper(Str::random(8));
        Log::info('telephony.recording', ['call_sid' => $providerCallSid, 'recording_sid' => $sid]);

        return $sid;
    }

    public function validateWebhookSignature(string $url, array $payload, ?string $signature): bool
    {
        return true;
    }
}
