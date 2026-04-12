<?php

namespace PersianKit\Tests\Unit\DateConversion;

use PHPUnit\Framework\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\DateConversion\JalaliFormatter;

class JalaliFormatterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_format_known_date_farvardin(): void
    {
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('apply_filters')->returnArg(2);

        // 21 March 2025 = 1 Farvardin 1404
        $timestamp = gmmktime(12, 0, 0, 3, 21, 2025);
        $result = JalaliFormatter::format('Y/m/d', $timestamp);

        $this->assertSame('1404/01/01', $result);
    }

    public function test_format_known_date_esfand(): void
    {
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('apply_filters')->returnArg(2);

        // 20 March 2025 = 30 Esfand 1403
        $timestamp = gmmktime(12, 0, 0, 3, 20, 2025);
        $result = JalaliFormatter::format('Y/m/d', $timestamp);

        $this->assertSame('1403/12/30', $result);
    }

    public function test_format_with_explicit_timezone(): void
    {
        Functions\when('apply_filters')->returnArg(2);

        // March 20 23:00 UTC = still March 20 in UTC = 30 Esfand 1403
        // But in Tehran (UTC+3:30) it's already March 21 = 1 Farvardin 1404
        $timestamp = gmmktime(23, 0, 0, 3, 20, 2025);
        $utcTz = new \DateTimeZone('UTC');
        $result = JalaliFormatter::format('Y/m/d', $timestamp, $utcTz);

        $this->assertSame('1403/12/30', $result);
    }

    public function test_format_with_string_timestamp(): void
    {
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('apply_filters')->returnArg(2);

        $result = JalaliFormatter::format('Y', '2025-03-21 12:00:00');

        $this->assertSame('1404', $result);
    }

    public function test_format_applies_filter(): void
    {
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));

        // Let apply_filters return a modified value
        Functions\when('apply_filters')->alias(function ($tag, ...$args) {
            if ($tag === 'persian_kit_date_display') {
                return 'FILTERED:' . $args[0];
            }
            return $args[0];
        });

        $timestamp = gmmktime(12, 0, 0, 3, 21, 2025);
        $result = JalaliFormatter::format('Y', $timestamp);

        $this->assertStringStartsWith('FILTERED:', $result);
    }

    public function test_gregorian_format(): void
    {
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));

        $timestamp = gmmktime(12, 0, 0, 3, 21, 2025);
        $result = JalaliFormatter::gregorianFormat('Y-m-d', $timestamp);

        $this->assertSame('2025-03-21', $result);
    }

    public function test_format_time_characters(): void
    {
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('apply_filters')->returnArg(2);

        // Use a known timestamp: March 21, 2025, 15:30:45 Tehran time
        // Tehran is UTC+3:30, so UTC would be 12:00:15
        $timestamp = gmmktime(12, 0, 45, 3, 21, 2025);
        $result = JalaliFormatter::format('H:i:s', $timestamp);

        $this->assertSame('15:30:45', $result);
    }

    public function test_format_short_day_and_month_tokens_remain_compatible(): void
    {
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('apply_filters')->returnArg(2);

        $timestamp = gmmktime(12, 0, 45, 3, 21, 2025);
        $result = JalaliFormatter::format('D j M Y', $timestamp);

        $this->assertSame('ج 1 فرو 1404', $result);
    }

    public function test_format_meridiem_tokens_remain_compatible(): void
    {
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('apply_filters')->returnArg(2);

        $timestamp = gmmktime(12, 0, 45, 3, 21, 2025);
        $result = JalaliFormatter::format('a A', $timestamp);

        $this->assertSame('ب.ظ بعد از ظهر', $result);
    }

    public function test_format_composite_tokens_remain_compatible(): void
    {
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('apply_filters')->returnArg(2);

        $timestamp = gmmktime(12, 0, 45, 3, 21, 2025);

        $this->assertSame('1404-01-01T15:30:45+03:30', JalaliFormatter::format('c', $timestamp));
        $this->assertSame('ج, 01 فرو 1404 15:30:45 +03:30', JalaliFormatter::format('r', $timestamp));
    }

    public function test_format_legacy_day_and_week_tokens_remain_compatible(): void
    {
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
        Functions\when('apply_filters')->returnArg(2);

        $timestamp = gmmktime(12, 0, 45, 3, 21, 2025);

        $this->assertSame('7 6 1 1 1404', JalaliFormatter::format('N w z W o', $timestamp));
    }
}
