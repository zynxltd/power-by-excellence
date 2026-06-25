<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Support\Http\ExternalRedirect;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AccountController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeSuperAdmin($request);

        return Inertia::render('Admin/Accounts/Index', [
            'accounts' => Account::withCount(['campaigns', 'leads', 'buyers', 'suppliers'])
                ->orderBy('name')
                ->get()
                ->map(fn (Account $account) => [
                    'id' => $account->id,
                    'name' => $account->brand_name ?: $account->name,
                    'slug' => $account->slug,
                    'domain' => $account->resolvedDomain(),
                    'portal_url' => TenantResolver::portalUrl($account, '/dashboard'),
                    'campaigns_count' => $account->campaigns_count,
                    'leads_count' => $account->leads_count,
                    'buyers_count' => $account->buyers_count,
                    'suppliers_count' => $account->suppliers_count,
                    'admin_user' => $account->users()->whereIn('role', [UserRole::AccountAdmin, UserRole::Staff])->orderBy('id')->first(['id', 'name', 'email']),
                ]),
            'currentAccountId' => session('current_account_id'),
        ]);
    }

    public function switch(Request $request): RedirectResponse
    {
        $this->authorizeSuperAdmin($request);

        $validated = $request->validate(['account_id' => 'required|exists:accounts,id']);
        session(['current_account_id' => $validated['account_id']]);

        return back()->with('success', 'Switched partner platform.');
    }

    public function visit(Request $request, int $accountId): RedirectResponse|HttpResponse
    {
        $this->authorizeSuperAdmin($request);

        $account = Account::findOrFail($accountId);
        session(['current_account_id' => $account->id, 'god_mode' => true]);

        if (TenantResolver::isCentralHost($request->getHost())) {
            $token = Str::random(48);
            Cache::put("god_mode_handoff:{$token}", [
                'super_admin_id' => $request->user()->id,
                'account_id' => $account->id,
            ], now()->addMinutes(2));

            return ExternalRedirect::away(
                $request,
                TenantResolver::portalUrl($account, "/god-mode/handoff/{$token}")
            );
        }

        return redirect()->route('dashboard')->with('success', 'Viewing '.$account->name.' in god mode.');
    }

    protected function authorizeSuperAdmin(Request $request): void
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);
    }
}
