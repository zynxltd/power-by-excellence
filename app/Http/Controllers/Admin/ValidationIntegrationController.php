<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\Admin\ResolvesAdminAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ValidationIntegrationController extends Controller
{
    use ResolvesAdminAccount;

    public function edit(Request $request): Response
    {
        $account = $this->resolveAdminAccount($request);
        $settings = $account->settings['validation_integration'] ?? [
            'enabled' => true,
            'email_validation' => true,
            'hlr_validation' => true,
            'quarantine_on_fail' => true,
        ];

        return Inertia::render('Admin/Integrations/Validation', [
            'settings' => $settings,
            'driver' => config('validation.driver'),
            'demoHints' => [
                'Reject email domains: invalid.demo, bounce.demo, trap.demo',
                'Use +trap in email to simulate spam trap',
                'Phone prefixes 07000, 08000 simulate unreachable HLR',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $account = $this->resolveAdminAccount($request);

        $validated = $request->validate([
            'enabled' => 'boolean',
            'email_validation' => 'boolean',
            'hlr_validation' => 'boolean',
            'quarantine_on_fail' => 'boolean',
        ]);

        $settings = $account->settings ?? [];
        $settings['validation_integration'] = array_merge(
            $settings['validation_integration'] ?? [],
            $validated
        );

        $account->update(['settings' => $settings]);

        return back()->with('success', 'Email & HLR validation settings saved.');
    }

    public function test(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => 'nullable|email',
            'phone' => 'nullable|string',
        ]);

        $provider = app(\App\Services\Validation\DemoValidationProvider::class);
        $results = [];

        if (! empty($validated['email'])) {
            $r = $provider->validateEmail($validated['email']);
            $results['email'] = ['passed' => $r->passed, 'reason' => $r->reason, 'meta' => $r->meta];
        }

        if (! empty($validated['phone'])) {
            $r = $provider->validateHlr($validated['phone']);
            $results['phone'] = ['passed' => $r->passed, 'reason' => $r->reason, 'meta' => $r->meta];
        }

        return back()->with('testResults', $results);
    }
}
