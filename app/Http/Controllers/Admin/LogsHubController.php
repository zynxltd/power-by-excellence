<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LogsHubController extends Controller
{
    public function index(Request $request): Response
    {
        $tab = $request->string('tab')->toString() ?: 'access';

        $tabs = [
            ['key' => 'access', 'label' => 'Access', 'route' => 'logs.access'],
            ['key' => 'delivery', 'label' => 'Delivery', 'route' => 'logs.delivery'],
            ['key' => 'api', 'label' => 'API', 'route' => 'logs.api'],
            ['key' => 'changes', 'label' => 'Changes', 'route' => 'logs.changes'],
            ['key' => 'security', 'label' => 'Security', 'route' => 'logs.security'],
        ];

        return Inertia::render('Admin/Logs/Hub', [
            'tab' => $tab,
            'tabs' => $tabs,
        ]);
    }
}
