<?php

namespace App\Services\Webhooks;

use Illuminate\Support\Str;

class WebhookSignatureService
{
    public const HEADER = 'X-Signature';

    public const ALGORITHM_PREFIX = 'sha256=';

    public static function generateSecret(): string
    {
        return 'whsec_'.Str::random(32);
    }

    public function sign(string $secret, string $body): string
    {
        return hash_hmac('sha256', $body, $secret);
    }

    public function headerValue(string $secret, string $body): string
    {
        return self::ALGORITHM_PREFIX.$this->sign($secret, $body);
    }

    /**
     * @return array<string, string>
     */
    public function headers(string $secret, string $body): array
    {
        return [self::HEADER => $this->headerValue($secret, $body)];
    }

    public function verify(string $secret, string $body, string $signatureHeader): bool
    {
        $expected = $this->headerValue($secret, $body);
        $provided = str_starts_with($signatureHeader, self::ALGORITHM_PREFIX)
            ? $signatureHeader
            : self::ALGORITHM_PREFIX.$signatureHeader;

        return hash_equals($expected, $provided);
    }

    public function encodePayload(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    }
}
