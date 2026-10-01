<?php

namespace PersianKit\Modules\DateConversion;

use PersianKit\Dependencies\Eram\Daynum\CivilDateTime;

defined('ABSPATH') || exit;

class JalaliFormatter
{
    private const MONTHS_LONG = [
        1  => 'فروردین',
        2  => 'اردیبهشت',
        3  => 'خرداد',
        4  => 'تیر',
        5  => 'مرداد',
        6  => 'شهریور',
        7  => 'مهر',
        8  => 'آبان',
        9  => 'آذر',
        10 => 'دی',
        11 => 'بهمن',
        12 => 'اسفند',
    ];

    private const MONTHS_SHORT = [
        1  => 'فرو',
        2  => 'ارد',
        3  => 'خرد',
        4  => 'تیر',
        5  => 'مرد',
        6  => 'شهر',
        7  => 'مهر',
        8  => 'آبا',
        9  => 'آذر',
        10 => 'دی',
        11 => 'بهم',
        12 => 'اسف',
    ];

    private const WEEKDAYS_LONG = [
        0 => 'یکشنبه',
        1 => 'دوشنبه',
        2 => 'سه‌شنبه',
        3 => 'چهارشنبه',
        4 => 'پنج‌شنبه',
        5 => 'جمعه',
        6 => 'شنبه',
    ];

    private const WEEKDAYS_SHORT = [
        0 => 'ی',
        1 => 'د',
        2 => 'س',
        3 => 'چ',
        4 => 'پ',
        5 => 'ج',
        6 => 'ش',
    ];

    private const NATIVE_TOKENS = [
        'B',
        'h',
        'H',
        'g',
        'G',
        'i',
        's',
        'I',
        'U',
        'u',
        'v',
        'Z',
        'O',
        'P',
        'p',
        'e',
        'T',
    ];

    /**
     * Format a timestamp as a Jalali (Shamsi) date string.
     *
     * Returns dates with English digits — digit conversion is a separate concern.
     */
    public static function format(string $format, int|string $timestamp = '', ?\DateTimeZone $timezone = null): string
    {
        return self::formatDateTime($format, self::resolveDateTime($timestamp, $timezone));
    }

    public static function formatDateTime(string $format, \DateTimeInterface $dateTime): string
    {
        $result = self::formatCompat($format, $dateTime, CivilDateTime::fromDateTime($dateTime));

        return apply_filters('persian_kit_date_display', $result, $format, $dateTime->getTimestamp(), $dateTime->getTimezone());
    }

    /**
     * Format a site-local MySQL datetime (post_date, comment_date), read in the
     * site timezone. Null when the value is empty, all zeros or unparseable.
     */
    public static function fromLocalMysql(string $format, ?string $local): ?string
    {
        $dateTime = self::parseMysql($local, wp_timezone());

        return $dateTime ? self::formatDateTime($format, $dateTime) : null;
    }

    /**
     * Format a UTC MySQL datetime (post_date_gmt, comment_date_gmt), shown in
     * $displayTimezone (the site timezone by default). Null when the value is
     * empty, all zeros (drafts) or unparseable.
     */
    public static function fromGmtMysql(string $format, ?string $gmt, ?\DateTimeZone $displayTimezone = null): ?string
    {
        $dateTime = self::parseMysql($gmt, new \DateTimeZone('UTC'));

        return $dateTime
            ? self::formatDateTime($format, $dateTime->setTimezone($displayTimezone ?? wp_timezone()))
            : null;
    }

    /**
     * Format a "timestamp with offset" as passed to the date_i18n filter: the
     * offset is already added, so its UTC wall clock is the local time.
     */
    public static function fromOffsetTimestamp(string $format, int $timestampWithOffset, bool $gmt = false): string
    {
        $timezone = $gmt ? new \DateTimeZone('UTC') : wp_timezone();
        $dateTime = new \DateTimeImmutable(gmdate('Y-m-d H:i:s', $timestampWithOffset), $timezone);

        return self::formatDateTime($format, $dateTime);
    }

    /**
     * Format a timestamp as a Gregorian date string.
     *
     * Escape hatch when tier 2 (wp_date hook) is active.
     */
    public static function gregorianFormat(string $format, int|string $timestamp = '', ?\DateTimeZone $timezone = null): string
    {
        return self::resolveDateTime($timestamp, $timezone)->format($format);
    }

    private static function parseMysql(?string $value, \DateTimeZone $timezone): ?\DateTimeImmutable
    {
        $value = trim((string) $value);

        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        try {
            return new \DateTimeImmutable($value, $timezone);
        } catch (\Exception) {
            return null;
        }
    }

    private static function resolveDateTime(int|string $timestamp, ?\DateTimeZone $timezone): \DateTime
    {
        $ts = self::resolveTimestamp($timestamp);
        $tz = $timezone ?? wp_timezone();

        $dateTime = new \DateTime('@' . $ts);
        $dateTime->setTimezone($tz);

        return $dateTime;
    }

    private static function resolveTimestamp(int|string $timestamp): int
    {
        if ($timestamp === '' || $timestamp === 0) {
            return time();
        }

        if (is_string($timestamp)) {
            return (int) strtotime($timestamp);
        }

        return $timestamp;
    }

    private static function formatCompat(string $format, \DateTimeInterface $dateTime, CivilDateTime $instant): string
    {
        $jalali = $instant->jalali();
        $year = $jalali->year();
        $month = $jalali->month();
        $day = $jalali->day();
        $weekday = (int) $dateTime->format('w');
        $dayOfYear = $jalali->dayOfYear();
        $jalaliWeekday = self::jalaliWeekdayNumber($weekday);

        $output = '';
        $length = strlen($format);

        // Preserve Persian Kit's historical morilog-style output while using
        // Daynum for the underlying calendar math.
        for ($i = 0; $i < $length; $i++) {
            $token = $format[$i];

            if ($token === '\\') {
                if ($i + 1 < $length) {
                    $output .= $format[$i + 1];
                    $i++;
                }
                continue;
            }

            if (in_array($token, self::NATIVE_TOKENS, true)) {
                $output .= $dateTime->format($token);
                continue;
            }

            $output .= match ($token) {
                'd' => sprintf('%02d', $day),
                'D' => self::WEEKDAYS_SHORT[$weekday],
                'j' => (string) $day,
                'l' => self::WEEKDAYS_LONG[$weekday],
                'N' => (string) $jalaliWeekday,
                'S' => 'ام',
                'w' => (string) ($jalaliWeekday - 1),
                'z' => (string) $dayOfYear,
                'W' => (string) (intdiv($dayOfYear, 7) + 1),
                'F' => self::MONTHS_LONG[$month],
                'm' => sprintf('%02d', $month),
                'M' => self::MONTHS_SHORT[$month],
                'n' => (string) $month,
                't' => (string) $jalali->daysInMonth(),
                'L' => $jalali->isLeapYear() ? '1' : '0',
                'o', 'Y' => (string) $year,
                'y' => sprintf('%02d', $year % 100),
                'a' => ((int) $dateTime->format('G') < 12) ? 'ق.ظ' : 'ب.ظ',
                'A' => ((int) $dateTime->format('G') < 12) ? 'قبل از ظهر' : 'بعد از ظهر',
                'c' => sprintf('%04d-%02d-%02dT%s', $year, $month, $day, $dateTime->format('H:i:sP')),
                'r' => sprintf(
                    '%s, %02d %s %d %s',
                    self::WEEKDAYS_SHORT[$weekday],
                    $day,
                    self::MONTHS_SHORT[$month],
                    $year,
                    $dateTime->format('H:i:s P')
                ),
                default => $token,
            };
        }

        return $output;
    }

    private static function jalaliWeekdayNumber(int $gregorianWeekday): int
    {
        return (($gregorianWeekday + 1) % 7) + 1;
    }
}
