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

    public function test_normalize_date_input_for_woo_save_converts_valid_jalali_date(): void
    {
        $this->assertSame('2026-03-21', WooDateHelper::normalizeDateInputForWooSave('1405-01-01'));
        $this->assertSame('2026-03-21', WooDateHelper::normalizeDateInputForWooSave('۱۴۰۵-۰۱-۰۱'));
    }

    public function test_normalize_date_input_for_woo_save_leaves_gregorian_and_unknown_values_untouched(): void
    {
        $this->assertSame('2026-03-21', WooDateHelper::normalizeDateInputForWooSave('2026-03-21'));
        $this->assertSame('foo', WooDateHelper::normalizeDateInputForWooSave('foo'));
        $this->assertSame('1405-13-01', WooDateHelper::normalizeDateInputForWooSave('1405-13-01'));
    }
}
