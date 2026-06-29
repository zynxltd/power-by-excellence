<?php

namespace App\Services\Telephony;

interface TelephonyGateway
{
    public function provider(): string;

    /**
     * @return array<int, array{sid: string, phone_number: string, friendly_name: string|null, locality: string|null}>
     */
    public function searchAvailableNumbers(string $areaCode, string $country = 'GB'): array;

    /**
     * @param  array{voice_url?: string, gather_url?: string, status_url?: string, recording_url?: string}  $webhooks
     * @return array{sid: string, phone_number: string, webhook_status: string}
     */
    public function purchaseNumber(string $phoneNumber, array $webhooks = []): array;

    /**
     * @return array{sid: string, phone_number: string, webhook_status?: string}
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
