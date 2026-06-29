<?php

namespace App\Services\Compliance;

use App\Models\Account;

class DataRetentionPolicy
{
    public const SETTINGS_KEY = 'data_retention';

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'purge_leads' => false,
            'leads_retention_days' => 365,
            'purge_logs' => false,
            'logs_retention_days' => 90,
            'purge_message_events' => false,
            'message_events_retention_days' => 90,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forAccount(Account $account): array
    {
        $stored = $account->settings[self::SETTINGS_KEY] ?? [];

        return array_merge(self::defaults(), is_array($stored) ? $stored : []);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function normalize(array $input): array
    {
        return [
            'purge_leads' => (bool) ($input['purge_leads'] ?? false),
            'leads_retention_days' => max(30, min(3650, (int) ($input['leads_retention_days'] ?? 365))),
            'purge_logs' => (bool) ($input['purge_logs'] ?? false),
            'logs_retention_days' => max(7, min(3650, (int) ($input['logs_retention_days'] ?? 90))),
            'purge_message_events' => (bool) ($input['purge_message_events'] ?? false),
            'message_events_retention_days' => max(7, min(3650, (int) ($input['message_events_retention_days'] ?? 90))),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function forInertia(Account $account): array
    {
        return self::forAccount($account);
    }
}
