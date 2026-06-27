<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Support\Auth\SignupVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AddressVerificationController extends Controller
{
    public function show(Request $request): RedirectResponse|Response
    {
        $user = $request->user();

        if (! SignupVerification::addressVerificationEnabled()) {
            return SignupVerification::redirectToNext($user);
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if (SignupVerification::phoneVerificationEnabled() && ! $user->hasVerifiedPhone()) {
            return redirect()->route('verification.phone');
        }

        if ($user->hasVerifiedAddress()) {
            return SignupVerification::redirectToNext($user);
        }

        $account = $user->resolveAccount();

        return Inertia::render('Auth/VerifyAddress', [
            'defaults' => [
                'address_line1' => $user->address_line1 ?? '',
                'address_line2' => $user->address_line2 ?? '',
                'city' => $user->city ?? '',
                'region' => $user->region ?? '',
                'postcode' => $user->postcode ?? '',
                'country' => $user->country ?? ($account?->default_country ?? 'GB'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (SignupVerification::phoneVerificationEnabled() && ! $user->hasVerifiedPhone()) {
            return redirect()->route('verification.phone');
        }

        $validated = $request->validate([
            'address_line1' => 'required|string|max:255',
            'address_line2' => 'nullable|string|max:255',
            'city' => 'required|string|max:120',
            'region' => 'nullable|string|max:120',
            'postcode' => 'required|string|max:16',
            'country' => 'required|string|size:2',
            'confirm_address' => 'accepted',
        ]);

        $user->forceFill([
            'address_line1' => $validated['address_line1'],
            'address_line2' => $validated['address_line2'] ?? null,
            'city' => $validated['city'],
            'region' => $validated['region'] ?? null,
            'postcode' => strtoupper(trim($validated['postcode'])),
            'country' => strtoupper($validated['country']),
            'address_verified_at' => now(),
        ])->save();

        return SignupVerification::redirectToNext(
            $user->fresh(),
            route('dashboard', absolute: false).'?verified=1'
        )->with('status', 'address-verified');
    }
}
