<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\PhoneVerificationService;
use App\Support\Auth\SignupVerification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PhoneVerificationController extends Controller
{
    public function show(Request $request): RedirectResponse|Response
    {
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if (! SignupVerification::phoneVerificationEnabled()) {
            return SignupVerification::redirectToNext($user);
        }

        if ($user->hasVerifiedPhone()) {
            return SignupVerification::redirectToNext($user);
        }

        return Inertia::render('Auth/VerifyPhone', [
            'phone' => $user->phone,
            'status' => session('status'),
            'smsEnabled' => SignupVerification::phoneVerificationEnabled(),
        ]);
    }

    public function send(Request $request, PhoneVerificationService $verification): RedirectResponse
    {
        if (! SignupVerification::phoneVerificationEnabled()) {
            return SignupVerification::redirectToNext($request->user());
        }

        $validated = $request->validate([
            'phone' => 'required|string|max:32',
        ]);

        $verification->sendCode($request->user(), $validated['phone']);

        return back()->with('status', 'verification-code-sent');
    }

    public function verify(Request $request, PhoneVerificationService $verification): RedirectResponse
    {
        if (! SignupVerification::phoneVerificationEnabled()) {
            return SignupVerification::redirectToNext($request->user());
        }

        $validated = $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $verification->verify($request->user(), $validated['code']);

        return SignupVerification::redirectToNext(
            $request->user()->fresh(),
            route('verification.address', absolute: false)
        )->with('status', 'phone-verified');
    }
}
