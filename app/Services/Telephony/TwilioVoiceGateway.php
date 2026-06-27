<?php

namespace App\Services\Telephony;

use Illuminate\Support\Str;
use Twilio\Rest\Client;
use Twilio\Security\RequestValidator;

class TwilioVoiceGateway implements TelephonyGateway
{
    protected Client $client;

    public function __construct()
    {
        $this->client = new Client(
            config('telephony.twilio.sid'),
            config('telephony.twilio.token'),
        );
    }

    public function provider(): string
    {
        return 'twilio';
    }

    public function provisionNumber(string $areaCode = '020'): array
    {
        $numbers = $this->client->availablePhoneNumbers('GB')
            ->local
            ->read(['contains' => $areaCode], 1);

        if (empty($numbers)) {
            throw new \RuntimeException('No available numbers for area code '.$areaCode);
        }

        $incoming = $this->client->incomingPhoneNumbers->create([
            'phoneNumber' => $numbers[0]->phoneNumber,
        ]);

        return [
            'sid' => $incoming->sid,
            'phone_number' => $incoming->phoneNumber,
        ];
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
