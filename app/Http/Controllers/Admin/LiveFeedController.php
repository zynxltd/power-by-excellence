<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Platform\PlatformLiveFeed;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LiveFeedController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        return Inertia::render('Admin/LiveFeed/Index', [
            'liveFeed' => app(PlatformLiveFeed::class)->paginate($request->integer('page', 1), 25),
        ]);
    }
}
