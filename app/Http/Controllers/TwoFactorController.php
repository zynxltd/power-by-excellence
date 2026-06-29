<?php

namespace App\Http\Controllers;

use App\Services\Security\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function enable(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $secret = $twoFactor->generateSecret();
        $request->session()->put('two_factor.pending_secret', $secret);

        return back()->with([
            'success' => 'Scan the QR code and enter a code to confirm.',
            'two_factor_qr' => $twoFactor->getQrCodeUrl($request->user(), $secret),
            'two_factor_secret' => $secret,
        ]);
    }

    public function confirm(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $secret = $request->session()->get('two_factor.pending_secret');

        if (! $secret || ! $twoFactor->verifyCode($secret, $request->string('code')->toString())) {
            return back()->withErrors(['code' => 'Invalid authentication code.']);
        }

        $recoveryCodes = $twoFactor->generateRecoveryCodes();
        $user = $request->user();

        $user->forceFill([
            'two_factor_enabled' => true,
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $recoveryCodes,
        ])->save();

        $request->session()->forget('two_factor.pending_secret');
        $request->session()->put('two_factor_verified', $user->id);

        return back()->with([
            'success' => 'Two-factor authentication enabled.',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    public function disable(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $user = $request->user();

        if ($twoFactor->mustKeepTwoFactor($user)) {
            return back()->withErrors([
                'password' => 'Two-factor authentication is required by your platform policy and cannot be disabled.',
            ]);
        }

        $user->forceFill([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        $request->session()->forget(['two_factor.pending_secret', 'two_factor_verified']);

        return back()->with('success', 'Two-factor authentication disabled.');
    }
}
