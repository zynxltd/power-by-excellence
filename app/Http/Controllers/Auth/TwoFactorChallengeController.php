<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Security\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class TwoFactorChallengeController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        $user = $this->resolvePartialUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        return Inertia::render('Auth/TwoFactorChallenge', [
            'email' => $user->email,
        ]);
    }

    public function store(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $user = $this->resolvePartialUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        $request->validate(['code' => ['required', 'string', 'size:6']]);

        if (! $twoFactor->verifyCode($user->two_factor_secret, $request->string('code')->toString())) {
            return back()->withErrors(['code' => 'Invalid authentication code.']);
        }

        return $this->completeLogin($request, $user);
    }

    public function recovery(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $user = $this->resolvePartialUser($request);

        if (! $user) {
            return redirect()->route('login');
        }

        $request->validate(['recovery_code' => ['required', 'string']]);

        $codes = $user->two_factor_recovery_codes ?? [];
        $submitted = strtoupper(trim($request->string('recovery_code')->toString()));
        $index = collect($codes)->search(fn ($code) => strtoupper($code) === $submitted);

        if ($index === false) {
            return back()->withErrors(['recovery_code' => 'Invalid recovery code.']);
        }

        unset($codes[$index]);
        $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

        return $this->completeLogin($request, $user);
    }

    protected function resolvePartialUser(Request $request): ?User
    {
        $userId = $request->session()->get('login.id');

        if (! $userId) {
            return null;
        }

        return User::find($userId);
    }

    protected function completeLogin(Request $request, User $user): RedirectResponse
    {
        $request->session()->forget('login.id');
        Auth::login($user, $request->session()->pull('login.remember', $request->boolean('remember')));
        $request->session()->regenerate();
        $request->session()->put('two_factor_verified', $user->id);

        $redirect = match ($user->role) {
            \App\Enums\UserRole::BuyerPortal => route('portal.buyer.dashboard'),
            \App\Enums\UserRole::SupplierPortal => route('portal.supplier.dashboard'),
            default => route('dashboard'),
        };

        return redirect()->intended($redirect);
    }
}
