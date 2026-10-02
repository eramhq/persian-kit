<?php

namespace PersianKit\Modules\DateConversion;

use PersianKit\Dependencies\Eram\Abzar\Digits\DigitConverter;
use PersianKit\Dependencies\Eram\Daynum\CivilDateTime;

defined('ABSPATH') || exit;

/**
 * Reads a date someone typed into a form field: Jalali or Gregorian, in
 * Persian, Arabic or English digits, written 1405-07-10, 1405/7/10 or
 * 1405.07.10. Years from 1200 to 1600 are Jalali and years from 1700 on
 * Gregorian; years in between are neither.
 *
 * Date fields with the picker submit Gregorian Y-m-d already. This covers
 * the same fields without JavaScript, and WooCommerce's admin date fields.
 */
final class DateInputParser
{
    private const PATTERN = '/^(?<year>\d{4})[-\/.](?<month>\d{1,2})[-\/.](?<day>\d{1,2})$/';

    /**
     * The date as Gregorian Y-m-d, or null when the value is not a valid date.
     */
    public static function toGregorian(string $value): ?string
    {
        if (!preg_match(self::PATTERN, DigitConverter::toEnglish(trim($value)), $matches)) {
            return null;
        }

        $year = (int) $matches['year'];
        $month = (int) $matches['month'];
        $day = (int) $matches['day'];

        if ($year >= 1700) {
            return checkdate($month, $day, $year) ? sprintf('%04d-%02d-%02d', $year, $month, $day) : null;
        }

        if ($year < 1200 || $year > 1600) {
            return null;
        }

        try {
            return CivilDateTime::fromJalali($year, $month, $day)->toDateTimeImmutable()->format('Y-m-d');
        } catch (\Throwable $exception) {
            return null;
        }
    }

    /**
     * For a field's submitted value: the Gregorian date when it is a date,
     * otherwise the value trimmed and with English digits, so the field's
     * own validation reports it.
     */
    public static function normalize(string $value): string
    {
        return self::toGregorian($value) ?? DigitConverter::toEnglish(trim($value));
    }
}
