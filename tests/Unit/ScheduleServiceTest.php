<?php

namespace Tests\Unit;

use App\Services\Scheduling\ScheduleService;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScheduleServiceTest extends TestCase
{
    #[Test]
    public function simple_buyer_schedule_format_is_supported(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-24 10:00:00', 'Europe/London'));

        $service = new ScheduleService;

        $this->assertTrue($service->isWithinSchedule([
            'enabled' => true,
            'timezone' => 'Europe/London',
            'start' => '09:00',
            'end' => '17:00',
        ]));

        $this->assertFalse($service->isWithinSchedule([
            'enabled' => true,
            'timezone' => 'Europe/London',
            'start' => '11:00',
            'end' => '17:00',
        ]));
    }

    #[Test]
    public function advanced_windows_schedule_is_supported(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-24 10:00:00', 'Europe/London'));

        $service = new ScheduleService;

        $this->assertTrue($service->isWithinSchedule([
            'timezone' => 'Europe/London',
            'windows' => [
                ['day' => 'wednesday', 'start' => '09:00', 'end' => '17:00'],
            ],
        ]));
    }

    #[Test]
    public function empty_schedule_means_always_available(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-24 23:59:00', 'Europe/London'));

        $service = new ScheduleService;

        $this->assertTrue($service->isWithinSchedule(null));
        $this->assertTrue($service->isWithinSchedule([]));
        $this->assertTrue($service->isWithinSchedule(['timezone' => 'Europe/London', 'windows' => []]));
    }

    #[Test]
    public function full_day_window_includes_last_minute_of_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-24 23:59:00', 'Europe/London'));

        $service = new ScheduleService;

        $this->assertTrue($service->isWithinSchedule([
            'timezone' => 'Europe/London',
            'windows' => [
                ['day' => 'all', 'start' => '00:00', 'end' => '23:59'],
            ],
        ]));
    }
}
