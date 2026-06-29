<?php

namespace App\Services\Compliance;

use App\Models\AccessLog;
use App\Models\Account;
use App\Models\AccountAuditLog;
use App\Models\ApiRequestLog;
use App\Models\DeliveryLog;
use App\Models\Lead;
use App\Models\LeadReturn;
use App\Models\MessageEvent;
use App\Models\SystemErrorLog;
use Illuminate\Support\Carbon;

class DataRetentionService
{
    /**
     * @var list<string>
     */
    protected array $piiFieldKeys = [
        'email', 'phone', 'phone1', 'phone2', 'mobile', 'firstname', 'lastname',
        'first_name', 'last_name', 'name', 'address', 'address1', 'address2',
        'zipcode', 'postcode', 'city', 'state', 'dob', 'date_of_birth', 'ssn',
    ];

    /**
     * @return array{leads_anonymized: int, logs_deleted: int, message_events_deleted: int}
     */
    public function purgeAccount(Account $account): array
    {
        $policy = DataRetentionPolicy::forAccount($account);

        return [
            'leads_anonymized' => $policy['purge_leads']
                ? $this->purgeExpiredLeads($account, (int) $policy['leads_retention_days'])
                : 0,
            'logs_deleted' => $policy['purge_logs']
                ? $this->purgeExpiredLogs($account, (int) $policy['logs_retention_days'])
                : 0,
            'message_events_deleted' => $policy['purge_message_events']
                ? $this->purgeExpiredMessageEvents($account, (int) $policy['message_events_retention_days'])
                : 0,
        ];
    }

    public function purgeExpiredLeads(Account $account, int $retentionDays): int
    {
        $cutoff = now()->subDays($retentionDays);
        $anonymized = 0;

        $query = Lead::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->where('received_at', '<', $cutoff)
            ->whereNull('metadata->anonymized_at')
            ->whereNotIn('id', LeadReturn::query()
                ->where('status', 'pending')
                ->select('lead_id'));

        foreach ($query->cursor() as $lead) {
            if ($this->anonymizeLead($lead)) {
                $anonymized++;
            }
        }

        return $anonymized;
    }

    public function purgeExpiredLogs(Account $account, int $retentionDays): int
    {
        $cutoff = $this->cutoff($retentionDays);
        $deleted = 0;

        $deleted += AccessLog::query()
            ->where('account_id', $account->id)
            ->where('created_at', '<', $cutoff)
            ->delete();

        $deleted += ApiRequestLog::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->where('created_at', '<', $cutoff)
            ->delete();

        $deleted += AccountAuditLog::query()
            ->where('account_id', $account->id)
            ->where('created_at', '<', $cutoff)
            ->delete();

        $deleted += SystemErrorLog::query()
            ->where('account_id', $account->id)
            ->where('created_at', '<', $cutoff)
            ->delete();

        $deleted += DeliveryLog::query()
            ->where('created_at', '<', $cutoff)
            ->whereHas('lead', fn ($q) => $q->where('account_id', $account->id))
            ->delete();

        return $deleted;
    }

    public function purgeExpiredMessageEvents(Account $account, int $retentionDays): int
    {
        $cutoff = $this->cutoff($retentionDays);

        return MessageEvent::withoutGlobalScopes()
            ->where('account_id', $account->id)
            ->where('created_at', '<', $cutoff)
            ->delete();
    }

    public function anonymizeLead(Lead $lead): bool
    {
        if (filled($lead->metadata['anonymized_at'] ?? null)) {
            return false;
        }

        if (LeadReturn::query()
            ->where('lead_id', $lead->id)
            ->where('status', 'pending')
            ->exists()) {
            return false;
        }

        $fieldData = $lead->field_data ?? [];

        foreach (array_keys($fieldData) as $key) {
            if ($this->isPiiField((string) $key)) {
                $fieldData[$key] = '[redacted]';
            }
        }

        $metadata = $lead->metadata ?? [];
        unset(
            $metadata['email_validation'],
            $metadata['hlr_validation'],
            $metadata['field_validation'],
        );
        $metadata['anonymized_at'] = now()->toIso8601String();

        $lead->update([
            'field_data' => $fieldData,
            'ip_address' => null,
            'user_agent' => null,
            'metadata' => $metadata,
        ]);

        return true;
    }

    protected function isPiiField(string $key): bool
    {
        $normalized = strtolower(str_replace(['-', ' '], '_', $key));

        if (in_array($normalized, $this->piiFieldKeys, true)) {
            return true;
        }

        return str_contains($normalized, 'email')
            || str_contains($normalized, 'phone')
            || str_contains($normalized, 'address')
            || str_contains($normalized, 'postcode')
            || str_contains($normalized, 'zip');
    }

    protected function cutoff(int $retentionDays): Carbon
    {
        return now()->subDays($retentionDays);
    }
}
