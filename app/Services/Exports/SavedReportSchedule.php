<?php

namespace App\Services\Exports;

use App\Models\SavedReport;
use Cron\CronExpression;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class SavedReportSchedule
{
    /**
     * @return array<string, array{label: string, cron: string}>
     */
    public static function presets(): array
    {
        return [
            'none' => ['label' => 'No schedule', 'cron' => ''],
            'daily_7am' => ['label' => 'Daily at 07:00', 'cron' => '0 7 * * *'],
            'daily_8am' => ['label' => 'Daily at 08:00', 'cron' => '0 8 * * *'],
            'weekly_monday_7am' => ['label' => 'Weekly — Monday 07:00', 'cron' => '0 7 * * 1'],
            'monthly_1st_8am' => ['label' => 'Monthly — 1st at 08:00', 'cron' => '0 8 1 * *'],
            'custom' => ['label' => 'Custom cron…', 'cron' => ''],
        ];
    }

    public static function cronFromPreset(?string $preset, ?string $customCron = null): ?string
    {
        if ($preset === null || $preset === '' || $preset === 'none') {
            return null;
        }

        if ($preset === 'custom') {
            $cron = trim((string) $customCron);

            return $cron !== '' ? self::validateCron($cron) : null;
        }

        $presets = self::presets();

        if (! isset($presets[$preset])) {
            throw new InvalidArgumentException("Unknown schedule preset: {$preset}");
        }

        $cron = $presets[$preset]['cron'];

        return $cron !== '' ? $cron : null;
    }

    public static function presetForCron(?string $cron): string
    {
        if (blank($cron)) {
            return 'none';
        }

        foreach (self::presets() as $key => $preset) {
            if ($key !== 'none' && $preset['cron'] === $cron) {
                return $key;
            }
        }

        return 'custom';
    }

    public static function validateCron(string $cron): string
    {
        $cron = trim($cron);

        try {
            CronExpression::factory($cron);
        } catch (\Throwable) {
            throw new InvalidArgumentException('Invalid cron expression.');
        }

        return $cron;
    }

    public static function nextRunAt(?string $cron, ?Carbon $from = null): ?Carbon
    {
        if (blank($cron)) {
            return null;
        }

        $expression = CronExpression::factory(self::validateCron($cron));
        $from = $from ?? now();

        return Carbon::instance($expression->getNextRunDate($from->copy()->subSecond()));
    }

    /**
     * @param  list<string>  $recipients
     * @return list<string>
     */
    public static function normalizeRecipients(array $recipients): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($email) => strtolower(trim((string) $email)),
            $recipients,
        ))));
    }

    public static function applyScheduleAttributes(array $attributes): array
    {
        $preset = $attributes['schedule_preset'] ?? null;
        unset($attributes['schedule_preset']);

        if (array_key_exists('schedule_cron', $attributes) && filled($attributes['schedule_cron'])) {
            $attributes['schedule_cron'] = self::validateCron((string) $attributes['schedule_cron']);
        } elseif ($preset !== null) {
            $attributes['schedule_cron'] = self::cronFromPreset(
                (string) $preset,
                $attributes['schedule_cron'] ?? null,
            );
        }

        if (array_key_exists('email_recipients', $attributes)) {
            $attributes['email_recipients'] = self::normalizeRecipients($attributes['email_recipients'] ?? []);
        }

        $cron = $attributes['schedule_cron'] ?? null;
        $attributes['next_run_at'] = filled($cron) && ! empty($attributes['email_recipients'] ?? null)
            ? self::nextRunAt($cron)
            : null;

        return $attributes;
    }

    public static function syncAfterRun(SavedReport $report, bool $success): void
    {
        $updates = [
            'last_run_at' => now(),
            'last_run_status' => $success ? 'success' : 'failed',
        ];

        if (filled($report->schedule_cron)) {
            $updates['next_run_at'] = self::nextRunAt($report->schedule_cron);
        }

        $report->update($updates);
    }

    public static function isDue(SavedReport $report): bool
    {
        if ($report->status !== 'active' || blank($report->schedule_cron)) {
            return false;
        }

        $recipients = $report->email_recipients ?? [];

        if ($recipients === []) {
            return false;
        }

        if ($report->next_run_at) {
            return $report->next_run_at->lte(now());
        }

        return CronExpression::factory($report->schedule_cron)->isDue(now());
    }
}
