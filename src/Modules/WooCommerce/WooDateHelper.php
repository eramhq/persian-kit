<?php

namespace PersianKit\Modules\WooCommerce;

use PersianKit\Dependencies\Morilog\Jalali\Jalalian;

defined('ABSPATH') || exit;

class WooDateHelper
{
    public static function normalizeDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
        ]);
    }

    public static function jalaliMonthToGregorianRange(string $jalaliYearMonth): ?array
    {
        $jalaliYearMonth = self::normalizeDigits(trim($jalaliYearMonth));

        if (!preg_match('/^\d{6}$/', $jalaliYearMonth)) {
            return null;
        }

        $year = (int) substr($jalaliYearMonth, 0, 4);
        $month = (int) substr($jalaliYearMonth, 4, 2);

        if ($month < 1 || $month > 12) {
            return null;
        }

        $start = new Jalalian($year, $month, 1);
        $end = new Jalalian($year, $month, $start->getMonthDays());

        return [
            'start' => $start->toCarbon()->format('Y-m-d'),
            'end' => $end->toCarbon()->format('Y-m-d'),
        ];
    }

    public static function toPersianDigits(string $value): string
    {
        return strtr($value, [
            '0' => '۰',
            '1' => '۱',
            '2' => '۲',
            '3' => '۳',
            '4' => '۴',
            '5' => '۵',
            '6' => '۶',
            '7' => '۷',
            '8' => '۸',
            '9' => '۹',
        ]);
    }

    public static function normalizeDateInputForWooSave(string $value): string
    {
        $value = self::normalizeDigits(trim($value));

        if ($value === '') {
            return '';
        }

        if (!preg_match('/^(?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2})$/', $value, $matches)) {
            return $value;
        }

        $year = (int) $matches['year'];
        $month = (int) $matches['month'];
        $day = (int) $matches['day'];

        if ($year >= 1700) {
            return $value;
        }

        if ($year < 1200 || $year > 1600) {
            return $value;
        }

        try {
            return (new Jalalian($year, $month, $day))->toCarbon()->format('Y-m-d');
        } catch (\Throwable $exception) {
            return $value;
        }
    }
}
