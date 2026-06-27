<?php

namespace App\Http\Middleware;

use App\Support\Products\CallLogicProduct;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureProductEnabled
{
    public function handle(Request $request, Closure $next, string $product): Response
    {
        $account = $request->attributes->get('account')
            ?? $request->user()?->resolveAccount();

        $enabled = match ($product) {
            'call_logic' => CallLogicProduct::isEnabled($account),
            default => false,
        };

        if (! $enabled) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Product not enabled on this account.'], 403);
            }

            return redirect()->route('settings.edit')
                ->with('error', 'Call Logic is not enabled on this account.');
        }

        return $next($request);
    }
}
