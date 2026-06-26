<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bridge Laravel session flash keys into Inertia v2 flash so client toast handlers fire.
 */
class BridgeSessionFlashToInertia
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession()) {
            foreach (['success', 'error', 'demo_success'] as $key) {
                $value = $request->session()->get($key);

                if (is_string($value) && $value !== '') {
                    Inertia::flash($key, $value);
                }
            }
        }

        return $next($request);
    }
}
