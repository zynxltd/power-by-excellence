<?php

namespace App\Http\Middleware;

use App\Models\HostedForm;
use App\Services\Forms\HostedFormEmbedService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HostedFormEmbedHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $slug = $request->route('slug');
        if (! is_string($slug)) {
            return $response;
        }

        $form = HostedForm::withoutGlobalScopes()->where('slug', $slug)->first();
        if (! $form) {
            return $response;
        }

        $csp = app(HostedFormEmbedService::class)->frameAncestorsDirective($form);
        if ($csp) {
            $response->headers->set('Content-Security-Policy', $csp);
        }

        return $response;
    }
}
