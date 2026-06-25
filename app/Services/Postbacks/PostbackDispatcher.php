<?php

namespace App\Services\Postbacks;

use App\Models\Lead;
use App\Models\Postback;
use App\Models\PostbackLog;
use App\Services\Delivery\TagInterpolator;
use App\Services\Logging\PlatformLogger;
use Illuminate\Support\Facades\Http;
use Throwable;

class PostbackDispatcher
{
    public function __construct(
        protected TagInterpolator $interpolator,
    ) {}

    public function dispatch(Lead $lead, string $event): void
    {
        $query = Postback::withoutGlobalScopes()
            ->where('account_id', $lead->account_id)
            ->where('is_active', true)
            ->where(function ($q) use ($lead) {
                $q->whereNull('campaign_id')->orWhere('campaign_id', $lead->campaign_id);
            })
            ->where(function ($q) use ($lead) {
                $q->whereNull('supplier_id');
                if ($lead->supplier_id) {
                    $q->orWhere('supplier_id', $lead->supplier_id);
                }
            });

        $postbacks = $query->get()->filter(fn (Postback $p) => in_array($event, $p->events ?? [], true));

        foreach ($postbacks as $postback) {
            $this->fire($postback, $lead, $event);
        }
    }

    protected function fire(Postback $postback, Lead $lead, string $event): void
    {
        $start = microtime(true);
        $payload = array_merge($lead->allFields(), [
            'lead_id' => $lead->uuid,
            'lead_uuid' => $lead->uuid,
            'campaign_id' => $lead->campaign_id,
            'campaign_reference' => $lead->campaign?->reference,
            'status' => $lead->status->value ?? $lead->status,
            'event' => $event,
            'revenue' => $lead->financials?->revenue,
            'payout' => $lead->financials?->payout,
            'sid' => $lead->sid,
            'ssid' => $lead->ssid,
        ]);

        $url = $this->buildUrl($postback, $payload);

        $log = PostbackLog::create([
            'postback_id' => $postback->id,
            'lead_id' => $lead->id,
            'event' => $event,
            'url_fired' => $url,
            'status' => 'pending',
        ]);

        try {
            $response = match (strtolower($postback->method ?? 'get')) {
                'post' => Http::timeout(8)->post($url, $payload),
                default => Http::timeout(8)->get($url),
            };

            $log->update([
                'status' => $response->successful() ? 'success' : 'failed',
                'http_status' => $response->status(),
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'response' => ['body' => substr($response->body(), 0, 500)],
            ]);

            PlatformLogger::leadEvent($lead, 'postback.sent', "Postback: {$postback->name}", [
                'postback_id' => $postback->id,
                'event' => $event,
                'status' => $response->status(),
            ]);
        } catch (Throwable $e) {
            $log->update([
                'status' => 'failed',
                'duration_ms' => (int) ((microtime(true) - $start) * 1000),
                'response' => ['error' => $e->getMessage()],
            ]);

            PlatformLogger::error('Postback failed', [
                'postback_id' => $postback->id,
                'event' => $event,
            ], $lead, $e);
        }
    }

    protected function buildUrl(Postback $postback, array $payload): string
    {
        $base = $this->interpolator->interpolate($postback->url, $payload);

        if (strtolower($postback->method ?? 'get') === 'get' && ! str_contains($base, '?')) {
            $params = http_build_query(
                collect($payload)
                    ->filter(fn ($v) => ! is_array($v) && $v !== null && $v !== '')
                    ->map(fn ($v) => (string) $v)
                    ->all()
            );

            return $base.(str_contains($base, '?') ? '&' : '?').$params;
        }

        if (strtolower($postback->method ?? 'get') === 'get' && str_contains($base, '?')) {
            $params = http_build_query(
                collect($payload)
                    ->filter(fn ($v) => ! is_array($v) && $v !== null && $v !== '')
                    ->map(fn ($v) => (string) $v)
                    ->all()
            );

            return $base.'&'.$params;
        }

        return $base;
    }
}
