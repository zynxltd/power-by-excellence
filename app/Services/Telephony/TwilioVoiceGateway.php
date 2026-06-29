<?php

namespace App\Services\Telephony;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Twilio\Rest\Client;
use Twilio\Security\RequestValidator;

class TwilioVoiceGateway implements TelephonyGateway
{
    protected Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client(
            config('telephony.twilio.sid'),
            config('telephony.twilio.token'),
        );
    }

    public function provider(): string
    {
        return 'twilio';
    }

    public function searchAvailableNumbers(string $areaCode, string $country = 'GB'): array
    {
        $results = $this->client->availablePhoneNumbers($country)
            ->local
            ->read(['contains' => $areaCode], (int) config('telephony.search_limit', 10));

        return collect($results)->map(fn ($number) => [
            'sid' => $number->phoneNumber,
            'phone_number' => $number->phoneNumber,
            'friendly_name' => $number->friendlyName ?? null,
            'locality' => $number->locality ?? null,
        ])->all();
    }

    public function purchaseNumber(string $phoneNumber, array $webhooks = []): array
    {
        $payload = ['phoneNumber' => $phoneNumber, 'voiceMethod' => 'POST'];

        if ($voiceUrl = $webhooks['voice_url'] ?? null) {
            $payload['voiceUrl'] = $voiceUrl;
        }

        if ($statusUrl = $webhooks['status_url'] ?? null) {
            $payload['statusCallback'] = $statusUrl;
            $payload['statusCallbackMethod'] = 'POST';
        }

        $incoming = $this->client->incomingPhoneNumbers->create($payload);

        return [
            'sid' => $incoming->sid,
            'phone_number' => $incoming->phoneNumber,
            'webhook_status' => ($webhooks['voice_url'] ?? null) ? 'configured' : 'pending',
        ];
    }

    public function provisionNumber(string $areaCode = '020'): array
    {
        $available = $this->searchAvailableNumbers($areaCode, config('telephony.default_country', 'GB'));

        if ($available === []) {
            throw new \RuntimeException('No available numbers for area code '.$areaCode);
        }

        return $this->purchaseNumber($available[0]['phone_number']);
    }

    public function releaseNumber(string $providerSid): void
    {
        $this->client->incomingPhoneNumbers($providerSid)->delete();
    }

    public function buildInboundTwiml(CallTwimlContext $context): string
    {
        return app(LogTelephonyGateway::class)->buildInboundTwiml($context);
    }

    public function transferCall(string $providerCallSid, string $destination, array $options = []): array
    {
        $call = $this->client->calls($providerCallSid)->update([
            'twiml' => '<Response><Dial'.($options['record'] ?? false ? ' record="record-from-answer"' : '').'>'
                .htmlspecialchars($destination).'</Dial></Response>',
        ]);

        return ['status' => $call->status, 'destination' => $destination];
    }

    public function startRecording(string $providerCallSid): ?string
    {
        $recording = $this->client->calls($providerCallSid)
            ->recordings
            ->create(['recordingChannels' => 'dual']);

        return $recording->sid;
    }

    public function validateWebhookSignature(string $url, array $payload, ?string $signature): bool
    {
        $token = config('telephony.twilio.token');

        if (! $token || ! $signature) {
            return false;
        }

        return (new RequestValidator($token))->validate($signature, $url, $payload);
    }
}
