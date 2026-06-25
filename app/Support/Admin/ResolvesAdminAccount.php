<?php

namespace App\Support\Admin;

use App\Models\Account;
use App\Support\Tenancy\AccountContext;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;

trait ResolvesAdminAccount
{
    protected function resolveAdminAccount(Request $request): Account
    {
        $account = $this->resolveOptionalAdminAccount($request);

        if ($account instanceof Account) {
            return $account;
        }

        if ($request->user()?->isSuperAdmin()) {
            throw new HttpResponseException(
                to_route('accounts.index')->with('error', 'Select a partner platform first.')
            );
        }

        abort(403, 'No platform context. Switch tenant or sign in on a partner domain.');
    }

    protected function resolveOptionalAdminAccount(Request $request): ?Account
    {
        $account = $request->attributes->get('account');

        if ($account instanceof Account) {
            return $account;
        }

        if ($id = AccountContext::id()) {
            $account = Account::find($id);
            if ($account) {
                return $account;
            }
        }

        $user = $request->user();

        if ($user?->account_id) {
            $account = Account::find($user->account_id);
            if ($account) {
                return $account;
            }
        }

        if ($user?->isSuperAdmin() && $request->session()->has('current_account_id')) {
            return Account::find($request->session()->get('current_account_id'));
        }

        return null;
    }
}
