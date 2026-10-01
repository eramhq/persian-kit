<?php

namespace PersianKit\Tests\Unit\DateConversion;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\DateConversion\JalaliPeriod;
use PHPUnit\Framework\TestCase;

class JalaliPeriodTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_years_below_1700_are_jalali(): void
    {
        $this->assertTrue(JalaliPeriod::isJalaliYear(1405));
        $this->assertTrue(JalaliPeriod::isJalaliYear(1699));
        $this->assertFalse(JalaliPeriod::isJalaliYear(1700));
        $this->assertFalse(JalaliPeriod::isJalaliYear(2026));
        $this->assertFalse(JalaliPeriod::isJalaliYear(0));
    }

    public function test_month_range_crosses_the_gregorian_month(): void
    {
        $this->assertSame(
            ['start' => '2026-09-23 00:00:00', 'end' => '2026-10-22 23:59:59'],
            JalaliPeriod::range(1405, 7)
        );
        $this->assertSame(
            ['start' => '2025-03-21 00:00:00', 'end' => '2025-04-20 23:59:59'],
            JalaliPeriod::range(1404, 1)
        );
    }

    public function test_esfand_of_a_leap_year_has_30_days(): void
    {
        $this->assertSame(
            ['start' => '2025-02-19 00:00:00', 'end' => '2025-03-20 23:59:59'],
            JalaliPeriod::range(1403, 12)
        );
        $this->assertSame(
            ['start' => '2030-02-19 00:00:00', 'end' => '2030-03-20 23:59:59'],
            JalaliPeriod::range(1408, 12)
        );
        $this->assertSame(30, JalaliPeriod::daysInMonth(1408, 12));
        $this->assertSame(29, JalaliPeriod::daysInMonth(1404, 12));
    }

    public function test_year_and_day_ranges(): void
    {
        $this->assertSame(
            ['start' => '2025-03-21 00:00:00', 'end' => '2026-03-20 23:59:59'],
            JalaliPeriod::range(1404)
        );
        $this->assertSame(
            ['start' => '2025-09-22 00:00:00', 'end' => '2025-09-22 23:59:59'],
            JalaliPeriod::range(1404, 6, 31)
        );
    }

    public function test_invalid_parts_have_no_range(): void
    {
        $this->assertNull(JalaliPeriod::range(1404, 12, 30));
        $this->assertNull(JalaliPeriod::range(1405, 13));
        $this->assertNull(JalaliPeriod::range(1405, 0));
        $this->assertNull(JalaliPeriod::range(1405, 7, 31));
        $this->assertNull(JalaliPeriod::range(1405, null, 9));
        $this->assertNull(JalaliPeriod::range(2026, 10));
        $this->assertNull(JalaliPeriod::toDateTime(1404, 12, 30));
    }

    public function test_from_gregorian_and_back(): void
    {
        $this->assertSame(['jy' => 1403, 'jm' => 12, 'jd' => 30], JalaliPeriod::fromGregorian(new \DateTimeImmutable('2025-03-20')));
        $this->assertSame(['jy' => 1404, 'jm' => 1, 'jd' => 1], JalaliPeriod::fromGregorian(new \DateTimeImmutable('2025-03-21')));

        $date = JalaliPeriod::toDateTime(1405, 7, 9);
        $this->assertNotNull($date);
        $this->assertSame('2026-10-01 12:00:00 Asia/Tehran', $date->format('Y-m-d H:i:s e'));
    }
}
