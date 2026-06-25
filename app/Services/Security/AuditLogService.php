<?php

namespace App\Services\Security;

use App\Models\AccountAuditLog;
use App\Support\Tenancy\AccountContext;

class AuditLogService
{
    public function record(string $action, ?string $entityType = null, ?int $entityId = null, ?array $changes = null): void
    {
        AccountAuditLog::create([
            'account_id' => AccountContext::id(),
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'changes' => $changes,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }
}
