<?php

namespace PersianKit\Modules\DateConversion;

use PersianKit\Dependencies\Eram\Daynum\CivilDateTime;

defined('ABSPATH') || exit;

/**
 * Jalali years, months and days as ranges of site-local Gregorian dates.
 */
class JalaliPeriod
{
    /**
     * Date parts below this year are read as Jalali, at or above it as Gregorian.
     */
    public const JALALI_YEAR_LIMIT = 1700;

    public static function isJalaliYear(int $year): bool
    {
        return $year > 0 && $year < self::JALALI_YEAR_LIMIT;
    }

    /**
     * Site-local Gregorian bounds (Y-m-d H:i:s) of a Jalali year, month or
     * day, or null when the parts are not a valid Jalali date.
     *
     * @return array{start: string, end: string}|null
     */
    public static function range(int $jy, ?int $jm = null, ?int $jd = null): ?array
    {
        if (!self::isJalaliYear($jy) || ($jm === null && $jd !== null)) {
            return null;
        }

        $firstMonth = $jm ?? 1;
        $lastMonth = $jm ?? 12;

        if (!CivilDateTime::isValidJalali($jy, $firstMonth, $jd ?? 1)) {
            return null;
        }

        $start = CivilDateTime::fromJalali($jy, $firstMonth, $jd ?? 1);
        $end = $jd !== null
            ? $start
            : CivilDateTime::fromJalali($jy, $lastMonth, 1)->jalali()->endOfMonth();

        return [
            'start' => self::gregorianDay($start) . ' 00:00:00',
            'end'   => self::gregorianDay($end) . ' 23:59:59',
        ];
    }

    /**
     * @return array{jy: int, jm: int, jd: int}
     */
    public static function fromGregorian(\DateTimeInterface $dateTime): array
    {
        $jalali = CivilDateTime::fromDateTime($dateTime)->jalali();

        return ['jy' => $jalali->year(), 'jm' => $jalali->month(), 'jd' => $jalali->day()];
    }

    /**
     * Noon, site time, on the given Jalali day, or null when it is not a
     * valid date. Noon keeps the calendar day clear of DST transitions.
     */
    public static function toDateTime(int $jy, int $jm, int $jd): ?\DateTimeImmutable
    {
        if (!self::isJalaliYear($jy) || !CivilDateTime::isValidJalali($jy, $jm, $jd)) {
            return null;
        }

        return new \DateTimeImmutable(
            self::gregorianDay(CivilDateTime::fromJalali($jy, $jm, $jd)) . ' 12:00:00',
            wp_timezone()
        );
    }

    public static function daysInMonth(int $jy, int $jm): int
    {
        return CivilDateTime::fromJalali($jy, $jm, 1)->jalali()->daysInMonth();
    }

    private static function gregorianDay(CivilDateTime $date): string
    {
        $gregorian = $date->gregorian();

        return sprintf('%04d-%02d-%02d', $gregorian->year(), $gregorian->month(), $gregorian->day());
    }
}
