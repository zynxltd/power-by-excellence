<?php

namespace App\Services\Calls;

use App\Enums\DeliveryMethod;
use App\Models\CallDeliveryLog;
use App\Models\CallSession;
use App\Models\Delivery;
use App\Services\Billing\RevenueCalculator;
use App\Services\Delivery\DeliveryResponseMatcher;
use Illuminate\Support\Facades\Http;

class CallPingService
{
    public function __construct(
        protected RevenueCalculator $revenueCalculator,
        protected DeliveryResponseMatcher $responseMatcher,
        protected CallEventLogger $logger,
    ) {}

    /**
     * @return array{accepted: bool, revenue: float, response: array<string, mixed>|null, duration_ms: int}
     */
    public function ping(CallSession $session, Delivery $delivery): array
    {
        $start = microtime(true);
        $config = $delivery->config ?? [];
        $pingUrl = $config['ping_url'] ?? null;

        $attributes = $session->callAttributes();
        $floor = (float) ($session->campaign?->floor_price ?? 0);

        $log = CallDeliveryLog::create([
            'call_session_id' => $session->id,
            'delivery_id' => $delivery->id,
            'buyer_id' => $delivery->buyer_id,
            'status' => 'pending',
            'tier' => $delivery->tier,
            'ping_request' => ['url' => $pingUrl, 'attributes' => $attributes, 'floor' => $floor],
        ]);

        if (! $pingUrl) {
            $log->update(['status' => 'skipped', 'skipped_reason' => 'missing_ping_url']);

            return ['accepted' => false, 'revenue' => 0, 'response' => null, 'duration_ms' => 0];
        }

        $timeoutMs = $session->campaign?->ping_timeout_ms ?? config('telephony.default_ping_timeout_ms', 800);
        $timeoutSec = max(1, (int) ceil($timeoutMs / 1000));

        try {
            $response = Http::timeout($timeoutSec)->post($pingUrl, array_merge($attributes, [
                'floor' => $floor,
                'call_uuid' => $session->uuid,
            ]));
            $body = $response->json() ?? ['raw' => $response->body()];
            $durationMs = (int) round((microtime(true) - $start) * 1000);

            $accepted = $this->responseMatcher->matchesPingSuccess($config, $body, $floor);
            $revenue = $accepted
                ? $this->revenueCalculator->calculate($delivery, $attributes, [], $body)
                : 0;

            $log->update([
                'status' => $accepted ? 'accepted' : 'rejected',
                'ping_response' => $body,
                'revenue' => $revenue,
                'duration_ms' => $durationMs,
            ]);

            return [
                'accepted' => $accepted,
                'revenue' => (float) $revenue,
                'response' => $body,
                'duration_ms' => $durationMs,
            ];
        } catch (\Throwable $e) {
            $durationMs = (int) round((microtime(true) - $start) * 1000);
            $log->update([
                'status' => 'failed',
                'skipped_reason' => $e->getMessage(),
                'duration_ms' => $durationMs,
            ]);

            return ['accepted' => false, 'revenue' => 0, 'response' => ['error' => $e->getMessage()], 'duration_ms' => $durationMs];
        }
    }

    public function isCallDelivery(Delivery $delivery): bool
    {
        return $delivery->method->isCallMethod()
            || filled($delivery->config['destination_phone'] ?? null);
    }

    public function destinationPhone(Delivery $delivery): ?string
    {
        return $delivery->config['destination_phone']
            ?? $delivery->config['transfer_number']
            ?? $delivery->buyer?->settings['call_destination'] ?? null;
    }
}
