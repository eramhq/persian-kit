<?php

namespace PersianKit\Modules\WooCommerce;

use PersianKit\Modules\DateConversion\JalaliPeriod;
use PersianKit\Service\Language\ContentLanguage;

defined('ABSPATH') || exit;

/**
 * A Jalali column (1404/07/09) after each date column in the Analytics
 * reports WooCommerce exports on the server and emails, next to the
 * Gregorian one. The CSV files built in the browser get the same column
 * from woocommerce-analytics-dates.js.
 *
 * WooCommerce writes a big export in batches, over several requests, and
 * the header last, so the date columns are known by name rather than by
 * their values: date, date_*, *_date, and WooCommerce's registered,
 * last_active, created and expires.
 */
class WooAnalyticsExport
{
    /** Date columns WooCommerce's own reports name without "date". */
    private const DATE_COLUMNS = ['registered', 'last_active', 'created', 'expires'];

    public function register(): void
    {
        // WooCommerce reads this map when an export starts, before its
        // columns: each report type there, extensions' too, gets the column.
        add_filter('woocommerce_export_report_controller_map', [$this, 'addFilters'], PHP_INT_MAX);
    }

    /**
     * @param mixed $map Report type => controller class.
     * @return mixed
     */
    public function addFilters($map)
    {
        if (!is_array($map) || !ContentLanguage::displaysPersian()) {
            return $map;
        }

        foreach (array_keys($map) as $type) {
            if (!is_string($type) || preg_match('/^[a-z0-9_-]+$/i', $type) !== 1) {
                continue;
            }

            add_filter("woocommerce_admin_{$type}_report_export_column_names", [$this, 'addColumns']);
            add_filter("woocommerce_export_admin_{$type}_report_row_data", [$this, 'addValues']);
        }

        return $map;
    }

    /**
     * @param mixed $columns Column id => label.
     * @return mixed
     */
    public function addColumns($columns)
    {
        if (!is_array($columns)) {
            return $columns;
        }

        $withJalali = [];
        foreach ($columns as $id => $label) {
            $withJalali[$id] = $label;
            if (is_string($id) && self::isDateColumn($id) && !isset($columns[$id . '_jalali'])) {
                /* translators: %s: the name of a date column in a report, such as Date. */
                $withJalali[$id . '_jalali'] = sprintf(__('%s (Jalali)', 'persian-kit'), is_string($label) ? $label : $id);
            }
        }

        return $withJalali;
    }

    /**
     * @param mixed $row Column id => value.
     * @return mixed
     */
    public function addValues($row)
    {
        if (!is_array($row)) {
            return $row;
        }

        foreach ($row as $id => $value) {
            if (is_string($id) && self::isDateColumn($id) && !isset($row[$id . '_jalali'])) {
                $row[$id . '_jalali'] = self::jalaliDate($value);
            }
        }

        return $row;
    }

    public static function isDateColumn(string $id): bool
    {
        if (str_ends_with($id, '_gmt') || str_ends_with($id, '_jalali')) {
            return false;
        }

        return $id === 'date' || str_starts_with($id, 'date_') || str_ends_with($id, '_date') || in_array($id, self::DATE_COLUMNS, true);
    }

    /**
     * 1404/07/09 for a value that starts with a Y-m-d date, else ''.
     */
    public static function jalaliDate(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:$|[ T])/', $value, $matches)
            || !checkdate((int) $matches[2], (int) $matches[3], (int) $matches[1])
        ) {
            return '';
        }

        $date = JalaliPeriod::fromGregorian(new \DateTimeImmutable("{$matches[1]}-{$matches[2]}-{$matches[3]}"));

        return sprintf('%04d/%02d/%02d', $date['jy'], $date['jm'], $date['jd']);
    }
}
