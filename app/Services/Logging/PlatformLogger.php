<?php

namespace App\Services\Logging;

use App\Models\Lead;
use App\Models\SystemErrorLog;
use App\Support\Tenancy\AccountContext;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class PlatformLogger
{
    public static function traceId(): string
    {
        return (string) Str::uuid();
    }

    public static function info(string $message, array $context = [], ?Lead $lead = null): void
    {
        static::write('info', $message, $context, $lead);
    }

    public static function warning(string $message, array $context = [], ?Lead $lead = null): void
    {
        static::write('warning', $message, $context, $lead);
    }

    public static function error(string $message, array $context = [], ?Lead $lead = null, ?Throwable $e = null): void
    {
        if ($e) {
            $context['exception'] = $e->getMessage();
            $context['trace'] = $e->getTraceAsString();
        }

        static::write('error', $message, $context, $lead);
    }

    public static function leadEvent(Lead $lead, string $eventType, string $message, array $payload = [], string $level = 'info'): void
    {
        if ($lead->exists) {
            $lead->events()->create([
                'event_type' => $eventType,
                'level' => $level,
                'message' => $message,
                'payload' => $payload,
            ]);
        }

        static::write($level, $message, array_merge($payload, ['lead_id' => $lead->id]), $lead, $eventType);
    }

    protected static function write(string $level, string $message, array $context, ?Lead $lead = null, ?string $eventContext = null): void
    {
        $traceId = $context['trace_id'] ?? static::traceId();
        $context['trace_id'] = $traceId;

        Log::channel('platform')->log($level, $message, $context);

        try {
            SystemErrorLog::withoutGlobalScopes()->create([
                'account_id' => $lead?->account_id ?? AccountContext::id(),
                'channel' => 'platform',
                'level' => $level,
                'context' => $eventContext ?? ($lead ? 'lead' : 'system'),
                'message' => $message,
                'payload' => $context,
                'trace_id' => $traceId,
            ]);
        } catch (Throwable) {
            // Never break pipeline for logging failures
        }
    }
}
