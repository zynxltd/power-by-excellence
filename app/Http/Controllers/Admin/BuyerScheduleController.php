<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Buyer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BuyerScheduleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Buyers/Schedule', [
            'buyers' => Buyer::withCount('deliveries')
                ->orderBy('name')
                ->get()
                ->map(fn (Buyer $buyer) => [
                    'id' => $buyer->id,
                    'name' => $buyer->name,
                    'reference' => $buyer->reference,
                    'status' => $buyer->status,
                    'schedule' => $buyer->schedule,
                    'caps' => $buyer->caps,
                    'deliveries_count' => $buyer->deliveries_count,
                ]),
        ]);
    }

    public function pause(Buyer $buyer): RedirectResponse
    {
        $buyer->update(['status' => 'inactive']);

        return back()->with('success', "{$buyer->name} paused.");
    }

    public function resume(Buyer $buyer): RedirectResponse
    {
        $buyer->update(['status' => 'active']);

        return back()->with('success', "{$buyer->name} resumed.");
    }

    public function overrideCaps(Request $request, Buyer $buyer): RedirectResponse
    {
        $validated = $request->validate([
            'daily_cap' => 'nullable|integer|min:0',
            'monthly_cap' => 'nullable|integer|min:0',
        ]);

        $caps = $buyer->caps ?? [];
        $overrides = $caps['today_override'] ?? [];

        if (isset($validated['daily_cap'])) {
            $overrides['daily'] = (int) $validated['daily_cap'];
        }

        if (isset($validated['monthly_cap'])) {
            $overrides['monthly'] = (int) $validated['monthly_cap'];
        }

        $overrides['date'] = now()->toDateString();
        $caps['today_override'] = $overrides;

        $buyer->update(['caps' => $caps]);

        return back()->with('success', "Today's cap override saved for {$buyer->name}.");
    }
}
