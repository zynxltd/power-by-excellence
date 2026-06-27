<?php

namespace App\ClickTrack\Concerns;

use App\Services\ClickTrack\ClickTrackEntitlementService;
use App\Support\Admin\ResolvesAdminAccount;
use Illuminate\Http\Request;

trait AuthorizesClickTrack
{
    use ResolvesAdminAccount;

    protected function authorizeClickTrack(Request $request): void
    {
        $account = $this->resolveAdminAccount($request);
        $entitlement = app(ClickTrackEntitlementService::class);

        abort_unless($entitlement->isEntitled($account), 403, 'Click Track is not enabled for this platform.');
    }
}
