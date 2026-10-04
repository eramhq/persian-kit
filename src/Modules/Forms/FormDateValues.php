<?php

namespace PersianKit\Modules\Forms;

use PersianKit\Dependencies\Eram\Daynum\CivilDateTime;
use PersianKit\Modules\DateConversion\DateInputParser;

defined('ABSPATH') || exit;

/**
 * Dates as form builders write them: day, month and year in the field's
 * own order, such as 02/10/2026 for d/m/Y. Shared by the form builder
 * integrations, whose fields keep submitting the Gregorian date in their
 * format while people see and type Jalali dates.
 */
final class FormDateValues
{
    /**
     * A date as Gregorian Y-m-d: written in the field's format, or year
     * first, Jalali or Gregorian, in any digits. Null when it isn't a date.
     *
     * @param string $format Day, month and year with -, / or . between them, such as d/m/Y.
     */
    public static function toGregorian(string $value, string $format): ?string
    {
        $parts = preg_split('/[-\/.]/', persian_kit_to_english_digits(trim($value)));
        if (!is_array($parts) || count($parts) !== 3) {
            return null;
        }

        // Typed year first, as the picker shows a date.
        $order = strlen($parts[0]) === 4 ? ['Y', 'm', 'd'] : str_split(str_replace(['-', '/', '.'], '', $format));
        $date = array_combine($order, $parts);

        if (!isset($date['Y'], $date['m'], $date['d'])) {
            return null;
        }

        return DateInputParser::toGregorian("{$date['Y']}-{$date['m']}-{$date['d']}");
    }

    /**
     * A Gregorian Y-m-d date in the field's format.
     */
    public static function inFormat(string $date, string $format): string
    {
        $dateTime = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);

        return $dateTime ? $dateTime->format($format) : $date;
    }

    /**
     * A Gregorian date's Jalali year, month and day, or null when it is not
     * a date.
     *
     * @return array{Y: int, m: int, d: int}|null
     */
    public static function jalaliParts(int $year, int $month, int $day): ?array
    {
        if (!checkdate($month, $day, $year)) {
            return null;
        }

        $jalali = CivilDateTime::fromGregorian($year, $month, $day)->jalali();

        return ['Y' => $jalali->year(), 'm' => $jalali->month(), 'd' => $jalali->day()];
    }
}
