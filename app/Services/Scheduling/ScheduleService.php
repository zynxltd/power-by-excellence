<?php

namespace App\Services\Scheduling;

use Carbon\Carbon;

class ScheduleService
{
    public function isWithinSchedule(?array $schedule): bool
    {
        if (empty($schedule)) {
            return true;
        }

        if (isset($schedule['enabled']) && ! $schedule['enabled']) {
            return true;
        }

        $schedule = $this->normalize($schedule);

        if (empty($schedule['windows'])) {
            return true;
        }

        $tz = $schedule['timezone'] ?? config('app.timezone');
        $now = Carbon::now($tz);
        $day = strtolower($now->format('l'));

        foreach ($schedule['windows'] as $window) {
            $windowDay = strtolower($window['day'] ?? '');
            if ($windowDay !== 'all' && $windowDay !== $day) {
                continue;
            }

            $start = $window['start'] ?? '00:00';
            $end = $window['end'] ?? '23:59';
            $current = $now->format('H:i');

            if ($current >= $start && $current <= $end) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{timezone?: string, windows: list<array{day: string, start: string, end: string}>}
     */
    public function normalize(?array $schedule): array
    {
        if (empty($schedule)) {
            return ['windows' => []];
        }

        if (! empty($schedule['windows'])) {
            return $schedule;
        }

        if (! empty($schedule['start']) && ! empty($schedule['end'])) {
            return [
                'timezone' => $schedule['timezone'] ?? config('app.timezone'),
                'windows' => [
                    ['day' => 'all', 'start' => $schedule['start'], 'end' => $schedule['end']],
                ],
            ];
        }

        return $schedule;
    }
}
