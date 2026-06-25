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
}
