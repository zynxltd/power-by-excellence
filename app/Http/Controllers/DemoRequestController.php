<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DemoRequestController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'company' => 'required|string|max:255',
            'message' => 'nullable|string|max:2000',
        ]);

        Log::channel('single')->info('Demo request received', $validated);

        return back()->with('demo_success', 'Thanks! Our team will contact you within one business day.');
    }
}
