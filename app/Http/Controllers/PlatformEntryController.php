<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlatformEntryController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        if ($user->isSuperAdmin()) {
            if ($accountId = $request->session()->get('current_account_id')) {
                $account = Account::query()->find($accountId);
                if ($account) {
                    return $this->redirectToTenant($request, $account, '/dashboard');
                }
            }

            return redirect()->route('dashboard');
        }

        $account = $user->resolveAccount();

        if (! $account) {
            return redirect()
                ->route('login')
                ->with('error', 'Your user account is not linked to a platform. Contact your administrator.');
        }

        $path = match (true) {
            $user->isBuyerPortal() => '/portal/buyer',
            $user->isSupplierPortal() => '/portal/supplier',
            default => '/dashboard',
        };

        return $this->redirectToTenant($request, $account, $path);
    }

    protected function redirectToTenant(Request $request, Account $account, string $path): RedirectResponse
    {
        $targetHost = TenantResolver::portalHost($account);

        if (strtolower($request->getHost()) === strtolower($targetHost)) {
            return redirect($path);
        }

        return redirect()->away(TenantResolver::portalUrl($account, $path));
    }
}
