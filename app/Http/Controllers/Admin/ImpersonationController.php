<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AccessLogService;
use App\Support\Http\ExternalRedirect;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ImpersonationController extends Controller
{
    public function start(Request $request, User $user): RedirectResponse|Response
    {
        $actor = $request->user();
        abort_unless($actor && $user->canBeImpersonatedBy($actor), 403);

        $redirectPath = $this->redirectPathFor($user);
        $account = $user->resolveAccount();
        $targetHost = $account ? TenantResolver::portalHost($account) : $request->getHost();

        if (! $account || $request->getHost() === $targetHost) {
            return $this->loginAs($request, $actor, $user, $redirectPath);
        }

        $token = Str::random(48);
        Cache::put("impersonation_handoff:{$token}", [
            'user_id' => $user->id,
            'impersonator_id' => $actor->id,
            'redirect' => $redirectPath,
        ], now()->addMinutes(2));

        app(AccessLogService::class)->record($actor, 'impersonation.start', $request);

        return ExternalRedirect::away(
            $request,
            TenantResolver::portalUrl($account, "/impersonate/handoff/{$token}")
        );
    }

    public function handoff(Request $request, string $token): RedirectResponse
    {
        $payload = Cache::pull("impersonation_handoff:{$token}");
        abort_unless($payload, 403, 'Invalid or expired impersonation link.');

        $user = User::findOrFail($payload['user_id']);
        $impersonator = User::findOrFail($payload['impersonator_id']);
        abort_unless($user->canBeImpersonatedBy($impersonator), 403);

        $hostAccount = $request->attributes->get('host_account');
        $userAccount = $user->resolveAccount();
        if ($hostAccount && $userAccount && $hostAccount->id !== $userAccount->id) {
            abort(403, 'Impersonation target does not belong to this platform domain.');
        }

        return $this->loginAs($request, $impersonator, $user, $payload['redirect'], recordAccess: false);
    }

    public function stop(Request $request): RedirectResponse|Response
    {
        if ($request->session()->get('god_mode') && ! $request->session()->get('impersonator_id')) {
            return $this->endGodMode($request);
        }

        $impersonatorId = $request->session()->get('impersonator_id')
            ?? Cache::pull('impersonation_active:'.$request->user()?->id);

        abort_unless($impersonatorId, 404, 'No active impersonation session.');

        $impersonator = User::findOrFail($impersonatorId);

        if (! TenantResolver::isCentralHost($request->getHost()) && $impersonator->isSuperAdmin()) {
            $token = Str::random(48);
            Cache::put("impersonation_stop_handoff:{$token}", [
                'impersonator_id' => $impersonatorId,
            ], now()->addMinutes(2));

            $request->session()->forget(['impersonator_id', 'god_mode']);
            Cache::forget('impersonation_active:'.$request->user()?->id);

            return ExternalRedirect::away(
                $request,
                'https://'.TenantResolver::baseDomain().'/impersonate/stop-handoff/'.$token
            );
        }

        app(AccessLogService::class)->record($impersonator, 'impersonation.stop', $request);

        $request->session()->forget('impersonator_id');
        Cache::forget('impersonation_active:'.$request->user()?->id);
        Auth::login($impersonator);
        $request->session()->regenerate();

        $redirect = TenantResolver::isCentralHost($request->getHost())
            ? route('dashboard')
            : TenantResolver::portalUrl($impersonator->resolveAccount() ?? $request->attributes->get('host_account'), '/dashboard');

        if ($request->header('X-Inertia') && ! TenantResolver::isCentralHost($request->getHost())) {
            return ExternalRedirect::away($request, $redirect);
        }

        return redirect()->away($redirect)->with('success', 'Impersonation ended.');
    }

    public function stopHandoff(Request $request, string $token): RedirectResponse
    {
        $payload = Cache::pull("impersonation_stop_handoff:{$token}");
        abort_unless($payload, 403, 'Invalid or expired impersonation link.');

        $impersonator = User::findOrFail($payload['impersonator_id']);
        app(AccessLogService::class)->record($impersonator, 'impersonation.stop', $request);

        Auth::login($impersonator);
        $request->session()->regenerate();
        $request->session()->forget(['impersonator_id', 'current_account_id', 'god_mode']);

        return redirect()->route('dashboard')->with('success', 'Impersonation ended.');
    }

    public function endGodMode(Request $request): RedirectResponse|Response
    {
        $user = $request->user();
        abort_unless($user?->isSuperAdmin(), 403);

        if (! TenantResolver::isCentralHost($request->getHost())) {
            $token = Str::random(48);
            Cache::put("god_mode_stop_handoff:{$token}", [
                'super_admin_id' => $user->id,
            ], now()->addMinutes(2));

            $request->session()->forget(['god_mode', 'current_account_id']);

            return ExternalRedirect::away(
                $request,
                'https://'.TenantResolver::baseDomain().'/god-mode/stop-handoff/'.$token
            );
        }

        $request->session()->forget(['god_mode', 'current_account_id']);

        return redirect()->route('command-center.index')->with('success', 'God mode ended.');
    }

    public function godModeStopHandoff(Request $request, string $token): RedirectResponse
    {
        $payload = Cache::pull("god_mode_stop_handoff:{$token}");
        abort_unless($payload, 403, 'Invalid or expired god mode link.');

        $user = User::findOrFail($payload['super_admin_id']);
        abort_unless($user->isSuperAdmin(), 403);

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->forget(['god_mode', 'current_account_id', 'impersonator_id']);

        return redirect()->route('command-center.index')->with('success', 'God mode ended.');
    }

    protected function loginAs(Request $request, User $impersonator, User $user, string $redirectPath, bool $recordAccess = true): RedirectResponse
    {
        $request->session()->put('impersonator_id', $impersonator->id);
        Cache::put("impersonation_active:{$user->id}", $impersonator->id, now()->addHours(8));
        Auth::login($user);
        $request->session()->regenerate();

        if ($recordAccess) {
            app(AccessLogService::class)->record($impersonator, 'impersonation.start', $request);
        }

        return redirect($redirectPath)->with('success', 'Now viewing as '.$user->name.'.');
    }

    protected function redirectPathFor(User $user): string
    {
        return match ($user->role) {
            UserRole::BuyerPortal => '/portal/buyer',
            UserRole::SupplierPortal => '/portal/supplier',
            default => '/dashboard',
        };
    }
}
