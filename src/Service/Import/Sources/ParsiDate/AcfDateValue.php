<?php

namespace PersianKit\Service\Import\Sources\ParsiDate;

use PersianKit\Dependencies\Eram\Abzar\Digits\DigitConverter;
use PersianKit\Dependencies\Eram\Daynum\CivilDateTime;
use PersianKit\Modules\DateConversion\DateInputParser;

defined('ABSPATH') || exit;

/**
 * One ACF date as ACF's date picker saves it: Gregorian Ymd.
 *
 * Parsi Date's date field saved a Gregorian Y-m-d, or the Jalali date as
 * typed while "save in Jalali" was on, so each value is read on its own:
 * years 1700 to 2100 are Gregorian, 1200 to 1600 Jalali, the years between
 * are neither. A date picker value after 2100 is one Parsi Date converted
 * twice (a Gregorian date read as Jalali); it is fixed only when asked.
 */
final class AcfDateValue
{
    public const CHANGED = 'changed';
    public const UNCHANGED = 'unchanged';
    public const ATTENTION = 'attention';

    /**
     * @return array{outcome: string, value: string, reason: string}
     */
    public static function convert(string $value, bool $fixTwice = false): array
    {
        $original = $value;
        $value = DigitConverter::toEnglish(trim($value));

        if ($value === '') {
            return self::result(self::UNCHANGED, $original);
        }

        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $value, $m)) {
            $value = "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        if (!preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})(?:\s.*)?$/', $value, $m)) {
            /* translators: %s: the saved value. */
            return self::result(self::ATTENTION, $original, sprintf(__('Not a date: %s', 'persian-kit'), $original));
        }

        $year = (int) $m[1];
        $date = "{$m[1]}-{$m[2]}-{$m[3]}";

        if ($year > 2100) {
            if (!$fixTwice) {
                return self::result(self::ATTENTION, $original, __('A date after 2100: Parsi Date probably converted it twice. Tick "Fix dates saved twice" to fix these.', 'persian-kit'));
            }

            // Its Jalali date is the Gregorian date that was meant.
            try {
                $jalali = CivilDateTime::fromGregorian($year, (int) $m[2], (int) $m[3])->jalali();
            } catch (\Throwable $error) {
                /* translators: %s: the saved value. */
                return self::result(self::ATTENTION, $original, sprintf(__('Not a date: %s', 'persian-kit'), $original));
            }
            $date = sprintf('%04d-%02d-%02d', $jalali->year(), $jalali->month(), $jalali->day());
            $year = $jalali->year();
        }

        if ($year > 1600 && $year < 1700) {
            /* translators: %s: the saved value. */
            return self::result(self::ATTENTION, $original, sprintf(__('Neither a Jalali nor a Gregorian year: %s', 'persian-kit'), $original));
        }

        $gregorian = DateInputParser::toGregorian($date);
        if ($gregorian === null) {
            /* translators: %s: the saved value. */
            return self::result(self::ATTENTION, $original, sprintf(__('Not a valid date: %s', 'persian-kit'), $original));
        }

        $ymd = str_replace('-', '', $gregorian);

        return $ymd === $original ? self::result(self::UNCHANGED, $original) : self::result(self::CHANGED, $ymd);
    }

    /**
     * @return array{outcome: string, value: string, reason: string}
     */
    private static function result(string $outcome, string $value, string $reason = ''): array
    {
        return ['outcome' => $outcome, 'value' => $value, 'reason' => $reason];
    }
}
