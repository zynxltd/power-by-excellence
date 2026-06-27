<?php

namespace App\Http\Controllers\Portal;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Services\Billing\AccountBillingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalBillingLockController extends Controller
{
    public function __invoke(Request $request, AccountBillingService $billing): Response
    {
        $user = $request->user();
        $account = $user->resolveAccount();

        abort_unless($account, 403);

        $portalType = match ($user->role) {
            UserRole::BuyerPortal => 'buyer',
            UserRole::SupplierPortal => 'supplier',
            default => abort(403),
        };

        return Inertia::render('Portal/Billing/Lock', [
            'billing' => $billing->summary($account),
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'display_name' => $account->brand_name ?: $account->name,
            ],
            'portalType' => $portalType,
        ]);
    }
}
