<?php

namespace App\Http\Controllers;

use App\Services\ClickTrack\ClickLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClickRedirectController extends Controller
{
    public function __invoke(string $token, Request $request, ClickLogService $clicks): RedirectResponse
    {
        $link = $clicks->resolveLink($token);

        abort_unless($link && $link->isActive(), 404);

        $subs = $clicks->subsFromRequest($request, $link);
        $click = $clicks->logClick($link, $request);
        $destination = $clicks->buildDestination($link, $click, $subs);

        return redirect()->away($destination);
    }
}
