<?php

namespace PersianKit\Tests\Unit\WooCommerce;

use PersianKit\Modules\WooCommerce\WooDateHelper;
use PHPUnit\Framework\TestCase;

class WooDateHelperTest extends TestCase
{
    public function test_jalali_month_to_gregorian_range_returns_expected_dates(): void
    {
        $range = WooDateHelper::jalaliMonthToGregorianRange('140501');

        $this->assertSame([
            'start' => '2026-03-21',
            'end' => '2026-04-20',
        ], $range);
    }

    public function test_jalali_month_to_gregorian_range_rejects_invalid_values(): void
    {
        $this->assertNull(WooDateHelper::jalaliMonthToGregorianRange(''));
        $this->assertNull(WooDateHelper::jalaliMonthToGregorianRange('140513'));
        $this->assertNull(WooDateHelper::jalaliMonthToGregorianRange('14ab01'));
    }

    public function test_to_persian_digits_converts_ascii_digits(): void
    {
        $this->assertSame('فروردین ۱۴۰۵', WooDateHelper::toPersianDigits('فروردین 1405'));
    }
}
