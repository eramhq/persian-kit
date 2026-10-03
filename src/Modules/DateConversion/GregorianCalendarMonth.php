<?php

namespace PersianKit\Modules\DateConversion;

defined('ABSPATH') || exit;

/**
 * With the Jalali calendar off, core's get_calendar() reads a Jalali
 * archive's year and month (1405/07, or ?m=140507) as Gregorian and shows an
 * empty month. While it runs, the $year and $monthnum globals, which core
 * prefers over $m, hold the Gregorian month that overlaps the Jalali one
 * most: the one its last day falls in (Mehr 1405, 23 September to
 * 22 October, shows October 2026).
 */
class GregorianCalendarMonth
{
    /** @var array{year: mixed, monthnum: mixed}|null */
    private ?array $saved = null;

    public function register(): void
    {
        add_filter('get_calendar_args', [$this, 'useGregorianMonth'], PHP_INT_MAX);
        add_filter('get_calendar', [$this, 'restoreJalaliMonth'], PHP_INT_MAX);
    }

    public function useGregorianMonth(mixed $args): mixed
    {
        global $year, $monthnum, $m;

        $month = $this->gregorianMonth($year, $monthnum, $m);
        if ($month === null) {
            return $args;
        }

        $this->saved ??= ['year' => $year, 'monthnum' => $monthnum];
        [$year, $monthnum] = $month;

        return $args;
    }

    public function restoreJalaliMonth(mixed $output): mixed
    {
        global $year, $monthnum;

        if ($this->saved !== null) {
            ['year' => $year, 'monthnum' => $monthnum] = $this->saved;
            $this->saved = null;
        }

        return $output;
    }

    /**
     * @return array{int, int}|null Gregorian year and month, or null when the
     *                              globals are not a Jalali month.
     */
    private function gregorianMonth(mixed $year, mixed $monthnum, mixed $m): ?array
    {
        $jy = is_numeric($year) ? (int) $year : 0;
        $jm = is_numeric($monthnum) ? (int) $monthnum : 0;

        if ($jy === 0 || $jm === 0) {
            $compact = preg_replace('/\D/', '', (string) $m);
            if (!is_string($compact) || strlen($compact) < 6) {
                return null;
            }

            $jy = (int) substr($compact, 0, 4);
            $jm = (int) substr($compact, 4, 2);
        }

        $range = JalaliPeriod::range($jy, $jm);
        if ($range === null) {
            return null;
        }

        return [(int) substr($range['end'], 0, 4), (int) substr($range['end'], 5, 2)];
    }
}
