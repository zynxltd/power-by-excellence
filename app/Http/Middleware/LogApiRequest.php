<?php

namespace App\Http\Middleware;

use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $started = hrtime(true);

        $response = $next($request);

        $durationMs = (int) round((hrtime(true) - $started) / 1_000_000);
        $statusCode = $response->getStatusCode();
        $apiKey = $request->attributes->get('api_key');
        $account = $request->attributes->get('account');

        $errorMessage = null;
        $summary = null;

        if ($statusCode >= 400) {
            $content = $response->getContent();
            if ($content && str_starts_with($content, '{')) {
                $decoded = json_decode($content, true);
                $errorMessage = $decoded['error'] ?? $decoded['message'] ?? substr($content, 0, 500);
                $summary = is_array($decoded) ? array_slice($decoded, 0, 8) : null;
            } else {
                $errorMessage = substr((string) $content, 0, 500) ?: "HTTP {$statusCode}";
            }
        }

        try {
            ApiRequestLog::create([
                'account_id' => $account?->id ?? $apiKey?->account_id,
                'api_key_id' => $apiKey?->id,
                'method' => $request->method(),
                'path' => '/'.$request->path(),
                'status_code' => $statusCode,
                'duration_ms' => $durationMs,
                'error_message' => $errorMessage,
                'response_summary' => $summary,
                'ip_address' => $request->ip(),
            ]);
        } catch (\Throwable) {
            // never block API responses due to logging
        }

        $response->headers->set('X-Response-Time-Ms', (string) $durationMs);

        return $response;
    }
}
