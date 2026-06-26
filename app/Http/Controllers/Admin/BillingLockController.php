<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Billing\AccountBillingService;
use App\Support\Admin\ResolvesAdminAccount;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingLockController extends Controller
{
    use ResolvesAdminAccount;

    public function __invoke(Request $request, AccountBillingService $billing): Response
    {
        $account = $this->resolveAdminAccount($request);

        return Inertia::render('Admin/Billing/Lock', [
            'billing' => $billing->summary($account),
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'display_name' => $account->brand_name ?: $account->name,
            ],
        ]);
    }
}
