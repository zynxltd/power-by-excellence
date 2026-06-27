<?php

namespace App\Services\Leads;

use App\Models\Campaign;
use App\Models\DedupeIndex;
use App\Models\Lead;
use App\Services\Logging\PlatformLogger;
use Illuminate\Support\Facades\DB;

class DedupeService
{
    public function __construct(
        protected FieldHashService $fieldHasher,
    ) {}

    public function isDuplicate(Lead $lead, Campaign $campaign): ?string
    {
        $config = $campaign->dedupe_config ?? [];
        $fields = $config['fields'] ?? ['email', 'phone1'];
        $rejectDays = (int) ($config['reject_days'] ?? 30);
        $crossCampaigns = $config['cross_campaign_ids'] ?? [$campaign->id];

        foreach ($fields as $field) {
            $value = $lead->getField($field);
            if (blank($value)) {
                continue;
            }

            $hash = $this->fieldHasher->resolveHash($field, (string) $value);
            if ($hash === null) {
                continue;
            }

            $exists = DedupeIndex::query()
                ->where('account_id', $lead->account_id)
                ->where('field_key', $field)
                ->where('field_value_hash', $hash)
                ->whereIn('campaign_id', $crossCampaigns)
                ->where('lead_id', '!=', $lead->id)
                ->where(function ($q) use ($rejectDays) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })
                ->exists();

            if ($exists) {
                PlatformLogger::leadEvent($lead, 'dedupe.rejected', "Duplicate detected on {$field}", ['field' => $field], 'warning');

                return "Duplicate {$field}";
            }
        }

        return null;
    }

    public function index(Lead $lead, Campaign $campaign): void
    {
        $config = $campaign->dedupe_config ?? [];
        $fields = $config['fields'] ?? ['email', 'phone1'];
        $rejectDays = (int) ($config['reject_days'] ?? 30);

        foreach ($fields as $field) {
            $value = $lead->getField($field);
            if (blank($value)) {
                continue;
            }

            $hash = $this->fieldHasher->resolveHash($field, (string) $value);
            if ($hash === null) {
                continue;
            }

            DedupeIndex::create([
                'account_id' => $lead->account_id,
                'campaign_id' => $campaign->id,
                'field_key' => $field,
                'field_value_hash' => $hash,
                'lead_id' => $lead->id,
                'expires_at' => $rejectDays > 0 ? now()->addDays($rejectDays) : null,
            ]);
        }
    }

    public function checkSuppression(Lead $lead): ?string
    {
        $fields = ['email', 'phone1'];

        foreach ($fields as $field) {
            $value = $lead->getField($field);
            if (blank($value)) {
                continue;
            }

            $hash = $this->fieldHasher->resolveHash($field, (string) $value);
            if ($hash === null) {
                continue;
            }

            $suppressed = DB::table('suppression_hashes')
                ->where('account_id', $lead->account_id)
                ->where('field_type', $field)
                ->where('hash', $hash)
                ->exists();

            if ($suppressed) {
                return "Suppressed {$field}";
            }
        }

        return null;
    }
}
