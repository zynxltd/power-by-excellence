<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Tenancy\TenantResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GodModeHandoffController extends Controller
{
    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $payload = \Illuminate\Support\Facades\Cache::pull("god_mode_handoff:{$token}");
        abort_unless($payload, 403, 'Invalid or expired god mode link.');

        $user = User::findOrFail($payload['super_admin_id']);
        abort_unless($user->isSuperAdmin(), 403, 'Not authorized for god mode.');

        $hostAccount = TenantResolver::resolveFromHost($request->getHost());
        abort_unless($hostAccount && $hostAccount->id === $payload['account_id'], 403, 'God mode link does not match this platform domain.');

        Auth::login($user);
        $request->session()->regenerate();
        session([
            'current_account_id' => $payload['account_id'],
            'god_mode' => true,
        ]);

        return redirect()->route('dashboard')->with('success', 'God mode - viewing '.$hostAccount->name);
    }
}
