<?php

namespace App\Tests\Unit\Helpers;

use App\Helpers\Utilities;
use App\Tests\TestCase;
use Illuminate\Support\Carbon;

class ScheduleNextRunDateTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_defaults_to_utc(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-01 00:00:00', 'UTC'));

        $next = Utilities::getScheduleNextRunDate('0', '3', '*', '*', '*');

        $this->assertSame('2026-07-01 03:00:00', $next->toDateTimeString());
        $this->assertSame('UTC', $next->timezoneName);
    }

    public function test_cron_is_evaluated_in_the_given_timezone_across_dst(): void
    {
        // Berlin is UTC+2 in summer (CEST) and UTC+1 in winter (CET).
        Carbon::setTestNow(Carbon::parse('2026-07-01 00:00:00', 'UTC'));
        $summer = Utilities::getScheduleNextRunDate('0', '3', '*', '*', '*', 'Europe/Berlin');

        Carbon::setTestNow(Carbon::parse('2026-12-01 00:00:00', 'UTC'));
        $winter = Utilities::getScheduleNextRunDate('0', '3', '*', '*', '*', 'Europe/Berlin');

        $this->assertSame('2026-07-01 01:00:00', $summer->toDateTimeString());
        $this->assertSame('UTC', $summer->timezoneName);
        $this->assertSame('2026-12-01 02:00:00', $winter->toDateTimeString());
    }

    public function test_spring_forward_does_not_skip_the_day(): void
    {
        // 2026-03-29 02:00 Berlin jumps to 03:00, so 03:00 local is 01:00 UTC that day.
        Carbon::setTestNow(Carbon::parse('2026-03-28 23:00:00', 'UTC'));

        $next = Utilities::getScheduleNextRunDate('0', '3', '*', '*', '*', 'Europe/Berlin');

        $this->assertSame('2026-03-29 01:00:00', $next->toDateTimeString());
    }
}
