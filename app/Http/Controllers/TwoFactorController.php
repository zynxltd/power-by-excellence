<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TwoFactorController extends Controller
{
    public function enable(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $user = $request->user();
        $recoveryCodes = collect(range(1, 8))->map(fn () => Str::upper(Str::random(10)))->all();

        $user->forceFill([
            'two_factor_enabled' => true,
            'two_factor_secret' => Str::random(32),
            'two_factor_recovery_codes' => $recoveryCodes,
        ])->save();

        return back()->with([
            'success' => 'Two-factor authentication enabled.',
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    public function disable(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $user = $request->user();
        $user->forceFill([
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        return back()->with('success', 'Two-factor authentication disabled.');
    }
}
