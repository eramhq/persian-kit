<?php

namespace PersianKit\Modules\WooCommerce;

use PersianKit\Dependencies\Morilog\Jalali\Jalalian;

defined('ABSPATH') || exit;

class WooDateHelper
{
    public static function jalaliMonthToGregorianRange(string $jalaliYearMonth): ?array
    {
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
}
