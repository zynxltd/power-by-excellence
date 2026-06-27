<?php

namespace App\Http\Controllers;

use App\Services\ClickTrack\ClickLogService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ImpressionPixelController extends Controller
{
    public function __invoke(string $token, Request $request, ClickLogService $clicks): Response
    {
        $link = $clicks->resolveLink($token);

        if ($link && $link->isActive()) {
            $clicks->logImpression($link, $request);
        }

        $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');

        return response($pixel, 200, [
            'Content-Type' => 'image/gif',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
