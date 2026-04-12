<?php

namespace PersianKit\Modules\WooCommerce;

use PersianKit\Dependencies\Eram\Daynum\Instant;
use PersianKit\Modules\DigitConversion\DigitConverter;

defined('ABSPATH') || exit;

class WooDateHelper
{
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

        $start = Instant::fromJalali($year, $month, 1);
        $end = $start->jalali()->endOfMonth();

        return [
            'start' => $start->toDateTimeImmutable()->format('Y-m-d'),
            'end' => $end->toDateTimeImmutable()->format('Y-m-d'),
        ];
    }

    public static function normalizeDateInputForWooSave(string $value): string
    {
        $value = DigitConverter::toEnglish(trim($value));

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
            return Instant::fromJalali($year, $month, $day)->toDateTimeImmutable()->format('Y-m-d');
        } catch (\Throwable $exception) {
            return $value;
        }
    }
}
