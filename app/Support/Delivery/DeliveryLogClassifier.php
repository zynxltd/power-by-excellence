<?php

namespace App\Support\Delivery;

use App\Models\DeliveryLog;
use Illuminate\Database\Eloquent\Builder;

class DeliveryLogClassifier
{
    /** @var list<string> */
    public const INTERNAL_REASONS = [
        'missing_ping_url',
        'missing_post_url',
        'timeout',
        'http_error',
        'exception',
    ];

    public static function isInternalFailure(DeliveryLog $log): bool
    {
        if ($log->status !== 'failed') {
            return false;
        }

        if (in_array($log->skipped_reason, self::INTERNAL_REASONS, true)) {
            return true;
        }

        if (data_get($log->post_response, 'error') || data_get($log->ping_response, 'error')) {
            return true;
        }

        if ($log->http_status && $log->http_status >= 500) {
            return true;
        }

        // Ping/post HTTP call returned nothing (timeout / connection error).
        if ($log->ping_request && ! $log->ping_response && ! $log->post_request) {
            return true;
        }

        return false;
    }

    public static function scopeInternalFailures(Builder $query): Builder
    {
        return $query->where('delivery_logs.status', 'failed')
            ->where(function (Builder $q) {
                $q->whereIn('delivery_logs.skipped_reason', self::INTERNAL_REASONS)
                    ->orWhere('delivery_logs.http_status', '>=', 500)
                    ->orWhereRaw("json_extract(delivery_logs.post_response, '$.error') IS NOT NULL")
                    ->orWhereRaw("json_extract(delivery_logs.ping_response, '$.error') IS NOT NULL")
                    ->orWhere(function (Builder $inner) {
                        $inner->whereNotNull('delivery_logs.ping_request')
                            ->whereNull('delivery_logs.ping_response')
                            ->whereNull('delivery_logs.post_request');
                    });
            });
    }
}
