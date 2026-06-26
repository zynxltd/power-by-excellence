<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Services\Leads\LeadRedirectService;
use Illuminate\Http\RedirectResponse;

class LeadRedirectController extends Controller
{
    public function __invoke(Lead $lead, LeadRedirectService $redirects): RedirectResponse
    {
        $destination = $redirects->follow($lead);

        abort_unless($destination, 404);

        return redirect()->away($destination);
    }
}
