<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Services\AccessLogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        $hostAccount = request()->attributes->get('host_account');

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'tenant' => $hostAccount?->publicBranding(),
            'isCentralHost' => \App\Support\Tenancy\TenantResolver::isCentralHost(),
            'centralLoginUrl' => 'https://'.\App\Support\Tenancy\TenantResolver::baseDomain().'/login',
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $user = $request->user();
        app(AccessLogService::class)->record($user, 'login', $request);

        $redirect = match ($user->role) {
            UserRole::BuyerPortal => route('portal.buyer.dashboard'),
            UserRole::SupplierPortal => route('portal.supplier.dashboard'),
            default => route('dashboard'),
        };

        return redirect()->intended($redirect);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        if ($user = $request->user()) {
            app(AccessLogService::class)->record($user, 'logout', $request);
        }

        $request->session()->forget([
            'impersonator_id',
            'current_account_id',
            'url.intended',
        ]);

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
