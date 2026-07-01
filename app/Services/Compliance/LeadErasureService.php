<?php

namespace App\Services\Compliance;

use App\Models\Lead;
use App\Models\LeadReturn;
use App\Models\User;
use App\Services\Security\AuditLogService;
use App\Support\Tenancy\AccountContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class LeadErasureService
{
    public function __construct(
        protected DataRetentionService $retention,
        protected AuditLogService $auditLog,
    ) {}

    /**
     * @return array{status: string, message?: string, lead: Lead}
     */
    public function requestErasure(Lead $lead, User $user, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('An erasure reason is required.');
        }

        $lead->refresh();

        if ($this->isAnonymized($lead)) {
            return [
                'status' => 'already_anonymized',
                'message' => 'Lead was already anonymized.',
                'lead' => $lead,
            ];
        }

        if ($blockMessage = $this->blockingReason($lead)) {
            return [
                'status' => 'blocked',
                'message' => $blockMessage,
                'lead' => $lead,
            ];
        }

        $metadata = $lead->metadata ?? [];
        $metadata['erasure'] = [
            'requested_at' => now()->toIso8601String(),
            'requested_by' => $user->id,
            'reason' => $reason,
            'completed_at' => null,
        ];
        $lead->update(['metadata' => $metadata]);
        $lead->refresh();

        if (! $this->retention->anonymizeLead($lead)) {
            return [
                'status' => 'blocked',
                'message' => 'Lead could not be anonymized while a dispute is open.',
                'lead' => $lead,
            ];
        }

        $lead->refresh();
        $metadata = $lead->metadata ?? [];
        $metadata['erasure']['completed_at'] = now()->toIso8601String();
        $lead->update(['metadata' => $metadata]);
        $lead->refresh();

        $lead->loadMissing('account');
        AccountContext::set($lead->account);
        $this->auditLog->record('lead.erasure', Lead::class, $lead->id, [
            'reason' => $reason,
            'requested_by' => $user->id,
        ]);

        return [
            'status' => 'completed',
            'message' => 'Lead PII has been erased.',
            'lead' => $lead,
        ];
    }

    public function isAnonymized(Lead $lead): bool
    {
        return filled($lead->metadata['anonymized_at'] ?? null);
    }

    public function blockingReason(Lead $lead): ?string
    {
        if (LeadReturn::query()
            ->where('lead_id', $lead->id)
            ->where('status', 'pending')
            ->exists()) {
            return 'A pending buyer return blocks erasure until the dispute is resolved.';
        }

        if ($this->hasPendingCallReturn($lead)) {
            return 'A pending call return blocks erasure until the dispute is resolved.';
        }

        return null;
    }

    protected function hasPendingCallReturn(Lead $lead): bool
    {
        if (! Schema::hasTable('call_returns') || ! Schema::hasTable('call_sessions')) {
            return false;
        }

        return DB::table('call_returns')
            ->join('call_sessions', 'call_sessions.id', '=', 'call_returns.call_session_id')
            ->where('call_sessions.lead_id', $lead->id)
            ->where('call_returns.status', 'pending')
            ->exists();
    }
}
