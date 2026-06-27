<?php

namespace App\Services\Telephony;

interface TelephonyGateway
{
    public function provider(): string;

    /**
     * @return array{sid: string, phone_number: string}
     */
    public function provisionNumber(string $areaCode = '020'): array;

    public function releaseNumber(string $providerSid): void;

    /**
     * @param  array<string, mixed>  $params
     */
    public function buildInboundTwiml(CallTwimlContext $context): string;

    /**
     * @param  array<string, mixed>  $options
     */
    public function transferCall(string $providerCallSid, string $destination, array $options = []): array;

    public function startRecording(string $providerCallSid): ?string;

    public function validateWebhookSignature(string $url, array $payload, ?string $signature): bool;
}
