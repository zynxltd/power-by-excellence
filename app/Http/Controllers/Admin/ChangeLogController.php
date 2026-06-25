<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LeadEvent;
use Inertia\Inertia;
use Inertia\Response;

class ChangeLogController extends Controller
{
    public function index(): Response
    {
        $events = LeadEvent::query()
            ->whereHas('lead')
            ->with(['lead:id,uuid,status,campaign_id', 'lead.campaign:id,name'])
            ->orderByDesc('created_at')
            ->paginate(30);

        return Inertia::render('Admin/Logs/ChangeLogs', [
            'events' => $events,
        ]);
    }
}
