<?php

namespace PersianKit\Modules\WooCommerce;

use PersianKit\Dependencies\Eram\Daynum\CivilDateTime;
use PersianKit\Dependencies\Eram\Abzar\Digits\DigitConverter;

defined('ABSPATH') || exit;

class WooDateHelper
{
    /**
     * Gregorian first/last day (Y-m-d) of a Jalali month given as "YYYYMM".
     *
     * @return array{start: string, end: string}|null
     */
    public static function jalaliMonthToGregorianRange(string $jalaliYearMonth): ?array
    {
        $jalaliYearMonth = DigitConverter::toEnglish(trim($jalaliYearMonth));

        if (!preg_match('/^\d{6}$/', $jalaliYearMonth)) {
            return null;
        }

        $year = (int) substr($jalaliYearMonth, 0, 4);
        $month = (int) substr($jalaliYearMonth, 4, 2);

        if ($month < 1 || $month > 12) {
            return null;
        }

        try {
            $start = CivilDateTime::fromJalali($year, $month, 1);
            $end = $start->jalali()->endOfMonth();
        } catch (\Throwable $exception) {
            return null;
        }

        return [
            'start' => $start->toDateTimeImmutable()->format('Y-m-d'),
            'end' => $end->toDateTimeImmutable()->format('Y-m-d'),
        ];
    }
}
