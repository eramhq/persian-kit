<?php

namespace PersianKit\Tests\Unit\Utilities;

use PHPUnit\Framework\TestCase;
use PersianKit\Modules\Utilities\TimeAgo;

class TimeAgoTest extends TestCase
{
    private const NOW = 1700000000;

    public function test_just_now(): void
    {
        $result = TimeAgo::format(self::NOW - 5, now: self::NOW, persianDigits: false);
        $this->assertSame('همین الان', $result);
    }

    public function test_few_seconds_ago(): void
    {
        $result = TimeAgo::format(self::NOW - 30, now: self::NOW, persianDigits: false);
        $this->assertSame('چند ثانیه پیش', $result);
    }

    public function test_minutes_ago(): void
    {
        $result = TimeAgo::format(self::NOW - 300, now: self::NOW, persianDigits: false);
        $this->assertSame('5 دقیقه پیش', $result);
    }

    public function test_one_hour_ago(): void
    {
        $result = TimeAgo::format(self::NOW - 3700, now: self::NOW, persianDigits: false);
        $this->assertSame('1 ساعت پیش', $result);
    }

    public function test_hours_ago(): void
    {
        $result = TimeAgo::format(self::NOW - 7200, now: self::NOW, persianDigits: false);
        $this->assertSame('2 ساعت پیش', $result);
    }

    public function test_days_ago(): void
    {
        $result = TimeAgo::format(self::NOW - 259200, now: self::NOW, persianDigits: false);
        $this->assertSame('حدود 3 روز پیش', $result);
    }

    public function test_weeks_ago(): void
    {
        $result = TimeAgo::format(self::NOW - 1209600, now: self::NOW, persianDigits: false);
        $this->assertSame('حدود 2 هفته پیش', $result);
    }

    public function test_months_ago(): void
    {
        $result = TimeAgo::format(self::NOW - 7776000, now: self::NOW, persianDigits: false);
        $this->assertSame('حدود 3 ماه پیش', $result);
    }

    public function test_years_ago(): void
    {
        $result = TimeAgo::format(self::NOW - 63072000, now: self::NOW, persianDigits: false);
        $this->assertSame('حدود 2 سال پیش', $result);
    }

    public function test_future_time(): void
    {
        $result = TimeAgo::format(self::NOW + 300, now: self::NOW, persianDigits: false);
        $this->assertSame('5 دقیقه بعد', $result);
    }

    public function test_persian_digits_enabled(): void
    {
        $result = TimeAgo::format(self::NOW - 300, now: self::NOW, persianDigits: true);
        $this->assertSame('۵ دقیقه پیش', $result);
    }

    public function test_datetime_input(): void
    {
        $dt = new \DateTimeImmutable('@' . (self::NOW - 300));
        $result = TimeAgo::format($dt, now: self::NOW, persianDigits: false);
        $this->assertSame('5 دقیقه پیش', $result);
    }

    public function test_string_input(): void
    {
        $result = TimeAgo::format('2023-11-14 22:13:20', now: self::NOW, persianDigits: false);
        // This is the same as NOW, so should be "همین الان"
        $this->assertSame('همین الان', $result);
    }

    public function test_invalid_string_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TimeAgo::format('not-a-date');
    }
}
