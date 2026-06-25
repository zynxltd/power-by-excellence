<?php

namespace App\Services;

use App\Models\AccessLog;
use App\Models\User;
use Illuminate\Http\Request;

class AccessLogService
{
    public function record(User $user, string $action, ?Request $request = null): AccessLog
    {
        $accountId = $user->account_id;
        if ($accountId && ! $user->relationLoaded('account')) {
            $user->load('account');
        }
        if ($accountId && ! $user->account) {
            $accountId = null;
        }

        return AccessLog::create([
            'account_id' => $accountId,
            'user_id' => $user->id,
            'action' => $action,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'path' => $request?->path(),
        ]);
    }
}
