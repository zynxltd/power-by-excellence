<?php

namespace App\Http\Middleware;

use App\Models\Account;
use App\Support\Tenancy\AccountContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetAccountFromUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->account_id) {
            $account = Account::find($user->account_id);
            AccountContext::set($account);
            $request->attributes->set('account', $account);
        } elseif ($user?->buyer_id) {
            $buyer = \App\Models\Buyer::withoutGlobalScopes()->find($user->buyer_id);
            AccountContext::set($buyer?->account);
            $request->attributes->set('account', $buyer?->account);
        } elseif ($user?->supplier_id) {
            $supplier = \App\Models\Supplier::withoutGlobalScopes()->find($user->supplier_id);
            AccountContext::set($supplier?->account);
            $request->attributes->set('account', $supplier?->account);
        } elseif ($user?->isSuperAdmin()) {
            $hostAccount = $request->attributes->get('host_account');
            if ($hostAccount instanceof Account) {
                AccountContext::set($hostAccount);
                $request->attributes->set('account', $hostAccount);
            } elseif ($request->session()->has('current_account_id')) {
                $account = Account::find($request->session()->get('current_account_id'));
                AccountContext::set($account);
                $request->attributes->set('account', $account);
            }
        }

        return $next($request);
    }
}
