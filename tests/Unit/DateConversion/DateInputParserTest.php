<?php

namespace PersianKit\Tests\Unit\DateConversion;

use PersianKit\Modules\DateConversion\DateInputParser;
use PHPUnit\Framework\TestCase;

class DateInputParserTest extends TestCase
{
    public function test_jalali_dates_become_gregorian(): void
    {
        $this->assertSame('2026-03-21', DateInputParser::toGregorian('1405-01-01'));
        $this->assertSame('2026-10-02', DateInputParser::toGregorian('1405-07-10'));
        $this->assertSame('2025-03-20', DateInputParser::toGregorian('1403-12-30'));
    }

    public function test_persian_and_arabic_digits_and_other_separators(): void
    {
        $this->assertSame('2026-03-21', DateInputParser::toGregorian('۱۴۰۵-۰۱-۰۱'));
        $this->assertSame('2026-10-02', DateInputParser::toGregorian('۱۴۰۵/۷/۱۰'));
        $this->assertSame('2026-10-02', DateInputParser::toGregorian('١٤٠٥/٠٧/١٠'));
        $this->assertSame('2026-10-02', DateInputParser::toGregorian('1405.07.10'));
        $this->assertSame('2026-03-21', DateInputParser::toGregorian(' ۱۴۰۵-۰۱-۰۱ '));
    }

    public function test_gregorian_dates_are_kept_and_padded(): void
    {
        $this->assertSame('2026-03-21', DateInputParser::toGregorian('2026-03-21'));
        $this->assertSame('2026-03-05', DateInputParser::toGregorian('2026/3/5'));
        $this->assertSame('2026-03-21', DateInputParser::toGregorian('۲۰۲۶-۰۳-۲۱'));
    }

    public function test_invalid_dates_are_rejected(): void
    {
        $this->assertNull(DateInputParser::toGregorian(''));
        $this->assertNull(DateInputParser::toGregorian('foo'));
        // 1404 is not a leap year.
        $this->assertNull(DateInputParser::toGregorian('1404-12-30'));
        $this->assertNull(DateInputParser::toGregorian('1405-07-31'));
        $this->assertNull(DateInputParser::toGregorian('1405-13-01'));
        $this->assertNull(DateInputParser::toGregorian('2026-02-29'));
        $this->assertNull(DateInputParser::toGregorian('20260321'));
        $this->assertNull(DateInputParser::toGregorian('1405-07-10 12:00'));
    }

    public function test_years_outside_both_calendars_are_rejected(): void
    {
        $this->assertNull(DateInputParser::toGregorian('1199-01-01'));
        $this->assertNull(DateInputParser::toGregorian('1650-01-01'));
        $this->assertNull(DateInputParser::toGregorian('0999-01-01'));
    }

    public function test_normalize_keeps_what_is_not_a_date(): void
    {
        $this->assertSame('2026-03-21', DateInputParser::normalize('۱۴۰۵-۰۱-۰۱'));
        $this->assertSame('', DateInputParser::normalize('   '));
        $this->assertSame('foo', DateInputParser::normalize('foo'));
        $this->assertSame('1405-13-01', DateInputParser::normalize('۱۴۰۵-۱۳-۰۱'));
        $this->assertSame('2026-02-31', DateInputParser::normalize('2026-02-31'));
    }
}
