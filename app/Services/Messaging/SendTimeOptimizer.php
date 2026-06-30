<?php

namespace App\Services\Messaging;

use App\Models\Account;
use App\Models\Lead;
use Carbon\Carbon;

class SendTimeOptimizer
{
    public const DEFAULT_QUIET_START = '21:00';

    public const DEFAULT_QUIET_END = '08:00';

    public const DEFAULT_OPTIMAL_HOUR = 9;

    /** @var array<string, string> */
    protected const US_STATE_TIMEZONES = [
        'AL' => 'America/Chicago',
        'AK' => 'America/Anchorage',
        'AZ' => 'America/Phoenix',
        'AR' => 'America/Chicago',
        'CA' => 'America/Los_Angeles',
        'CO' => 'America/Denver',
        'CT' => 'America/New_York',
        'DE' => 'America/New_York',
        'FL' => 'America/New_York',
        'GA' => 'America/New_York',
        'HI' => 'Pacific/Honolulu',
        'ID' => 'America/Boise',
        'IL' => 'America/Chicago',
        'IN' => 'America/Indiana/Indianapolis',
        'IA' => 'America/Chicago',
        'KS' => 'America/Chicago',
        'KY' => 'America/New_York',
        'LA' => 'America/Chicago',
        'ME' => 'America/New_York',
        'MD' => 'America/New_York',
        'MA' => 'America/New_York',
        'MI' => 'America/Detroit',
        'MN' => 'America/Chicago',
        'MS' => 'America/Chicago',
        'MO' => 'America/Chicago',
        'MT' => 'America/Denver',
        'NE' => 'America/Chicago',
        'NV' => 'America/Los_Angeles',
        'NH' => 'America/New_York',
        'NJ' => 'America/New_York',
        'NM' => 'America/Denver',
        'NY' => 'America/New_York',
        'NC' => 'America/New_York',
        'ND' => 'America/Chicago',
        'OH' => 'America/New_York',
        'OK' => 'America/Chicago',
        'OR' => 'America/Los_Angeles',
        'PA' => 'America/New_York',
        'RI' => 'America/New_York',
        'SC' => 'America/New_York',
        'SD' => 'America/Chicago',
        'TN' => 'America/Chicago',
        'TX' => 'America/Chicago',
        'UT' => 'America/Denver',
        'VT' => 'America/New_York',
        'VA' => 'America/New_York',
        'WA' => 'America/Los_Angeles',
        'WV' => 'America/New_York',
        'WI' => 'America/Chicago',
        'WY' => 'America/Denver',
        'DC' => 'America/New_York',
    ];

    /**
     * @return array{
     *     send_time_optimization: bool,
     *     quiet_hours_start: string,
     *     quiet_hours_end: string,
     *     optimal_send_hour: int,
     * }
     */
    public function settings(Account $account): array
    {
        $messaging = $account->settings['messaging'] ?? [];

        return [
            'send_time_optimization' => (bool) ($messaging['send_time_optimization'] ?? false),
            'quiet_hours_start' => (string) ($messaging['quiet_hours_start'] ?? self::DEFAULT_QUIET_START),
            'quiet_hours_end' => (string) ($messaging['quiet_hours_end'] ?? self::DEFAULT_QUIET_END),
            'optimal_send_hour' => max(0, min(23, (int) ($messaging['optimal_send_hour'] ?? self::DEFAULT_OPTIMAL_HOUR))),
        ];
    }

    /**
     * @param  array{
     *     send_time_optimization?: bool,
     *     quiet_hours_start?: string,
     *     quiet_hours_end?: string,
     *     optimal_send_hour?: int,
     * }  $validated
     * @return array<string, mixed>
     */
    public function mergeSettingsIntoAccount(Account $account, array $validated): array
    {
        $settings = $account->settings ?? [];
        $messaging = $settings['messaging'] ?? [];

        $messaging['send_time_optimization'] = (bool) ($validated['send_time_optimization'] ?? false);
        $messaging['quiet_hours_start'] = $validated['quiet_hours_start'] ?? self::DEFAULT_QUIET_START;
        $messaging['quiet_hours_end'] = $validated['quiet_hours_end'] ?? self::DEFAULT_QUIET_END;
        $messaging['optimal_send_hour'] = max(0, min(23, (int) ($validated['optimal_send_hour'] ?? self::DEFAULT_OPTIMAL_HOUR)));

        $settings['messaging'] = $messaging;

        return $settings;
    }

    public function resolveLeadTimezone(Lead $lead, Account $account): string
    {
        $fields = $lead->field_data ?? [];
        $timezone = $fields['timezone'] ?? null;

        if (is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)) {
            return $timezone;
        }

        $state = strtoupper(trim((string) ($fields['state'] ?? $fields['state_code'] ?? '')));

        if ($state !== '' && isset(self::US_STATE_TIMEZONES[$state])) {
            return self::US_STATE_TIMEZONES[$state];
        }

        return filled($account->timezone) ? (string) $account->timezone : 'UTC';
    }

    /**
     * Returns UTC send time when the message should be deferred, or null to send immediately.
     */
    public function computeSendAt(Account $account, ?Lead $lead, ?Carbon $reference = null): ?Carbon
    {
        $settings = $this->settings($account);

        if (! $settings['send_time_optimization'] || ! $lead) {
            return null;
        }

        $local = ($reference ?? now())->copy()->timezone($this->resolveLeadTimezone($lead, $account));

        if ($this->isInQuietHours($local, $settings['quiet_hours_start'], $settings['quiet_hours_end'])) {
            return $this->nextOptimalSendAt($local, $settings)->utc();
        }

        if ($local->hour < $settings['optimal_send_hour']) {
            $target = $local->copy()->setTime($settings['optimal_send_hour'], 0, 0);

            if ($this->isInQuietHours($target, $settings['quiet_hours_start'], $settings['quiet_hours_end'])) {
                return $this->nextOptimalSendAt($local, $settings)->utc();
            }

            return $target->utc();
        }

        return null;
    }

    public function isInQuietHours(Carbon $local, string $start, string $end): bool
    {
        [$startHour, $startMinute] = $this->parseTime($start);
        [$endHour, $endMinute] = $this->parseTime($end);

        $minutes = ($local->hour * 60) + $local->minute;
        $startMinutes = ($startHour * 60) + $startMinute;
        $endMinutes = ($endHour * 60) + $endMinute;

        if ($startMinutes < $endMinutes) {
            return $minutes >= $startMinutes && $minutes < $endMinutes;
        }

        return $minutes >= $startMinutes || $minutes < $endMinutes;
    }

    /**
     * @param  array{quiet_hours_start: string, quiet_hours_end: string, optimal_send_hour: int}  $settings
     */
    protected function nextOptimalSendAt(Carbon $local, array $settings): Carbon
    {
        $optimalHour = $settings['optimal_send_hour'];

        for ($dayOffset = 0; $dayOffset < 3; $dayOffset++) {
            $candidate = $local->copy()->addDays($dayOffset)->setTime($optimalHour, 0, 0);

            if ($candidate->lte($local)) {
                continue;
            }

            if (! $this->isInQuietHours($candidate, $settings['quiet_hours_start'], $settings['quiet_hours_end'])) {
                return $candidate;
            }
        }

        return $local->copy()->addDay()->setTime($optimalHour, 0, 0);
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function parseTime(string $time): array
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches)) {
            return [0, 0];
        }

        return [(int) $matches[1], (int) $matches[2]];
    }
}
