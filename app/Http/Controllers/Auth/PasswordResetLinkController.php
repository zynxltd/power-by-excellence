<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
            'tenant' => $this->tenantBranding(),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $hostAccount = $request->attributes->get('host_account');
        $user = User::where('email', $request->input('email'))->first();

        if ($hostAccount && $user && ! $user->belongsToAccount($hostAccount)) {
            throw ValidationException::withMessages([
                'email' => 'No account found for this email on this platform.',
            ]);
        }

        if (\App\Support\Tenancy\TenantResolver::isCentralHost($request->getHost()) && $user && ! $user->isSuperAdmin()) {
            $account = $user->resolveAccount();
            throw ValidationException::withMessages([
                'email' => $account
                    ? 'Reset your password at '.$account->portalUrl('/forgot-password')
                    : 'Use your partner platform domain to reset your password.',
            ]);
        }

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status == Password::RESET_LINK_SENT) {
            return back()->with('status', __($status));
        }

        throw ValidationException::withMessages([
            'email' => [trans($status)],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function tenantBranding(): ?array
    {
        $hostAccount = request()->attributes->get('host_account');
        if (! $hostAccount) {
            return null;
        }

        return $hostAccount->publicBranding();
    }
}
