<?php

namespace PersianKit\Modules\DateConversion;

use PersianKit\Dependencies\Eram\Daynum\CivilDateTime;

defined('ABSPATH') || exit;

/**
 * Serves date archives for Jalali years, months and days: /1405/07/ lists the
 * posts of Mehr 1405. A year below 1700 is read as Jalali; Gregorian archives
 * are left to WordPress.
 *
 * The date parts are swapped for a Gregorian date_query before the query runs
 * and put back afterwards, so is_month(), get_query_var('monthnum') and the
 * $year and $monthnum globals still describe the Jalali archive.
 */
class JalaliDateArchive
{
    public const QUERY_VAR = 'persian_kit_jalali_date';

    private const DATE_VARS = ['year', 'monthnum', 'day', 'm'];

    public function register(): void
    {
        add_filter('wp_checkdate', [$this, 'filterCheckdate'], 10, 2);
        add_action('pre_get_posts', [$this, 'filterQuery']);
        add_filter('posts_pre_query', [$this, 'restoreDateVars'], 10, 2);
    }

    /**
     * WP_Query and redirect_canonical() check a day archive's date as
     * Gregorian, which would turn 31 Shahrivar into a 404 or a redirect.
     * Both pass the date as zero-padded Y-m-d with no time.
     */
    public function filterCheckdate(mixed $valid, mixed $sourceDate): mixed
    {
        if (!is_string($sourceDate) || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $sourceDate, $matches)) {
            return $valid;
        }

        $year = (int) $matches[1];
        if (!JalaliPeriod::isJalaliYear($year)) {
            return $valid;
        }

        return CivilDateTime::isValidJalali($year, (int) $matches[2], (int) $matches[3]);
    }

    public function filterQuery(\WP_Query $query): void
    {
        if (is_admin()) {
            return;
        }

        $parts = $this->jalaliParts($query);
        if ($parts === null) {
            return;
        }

        $range = JalaliPeriod::range($parts['year'], $parts['month'], $parts['day']);
        if ($range === null) {
            return;
        }

        $original = [];
        foreach (self::DATE_VARS as $var) {
            $original[$var] = $query->get($var);
            $query->set($var, '');
        }

        $dateQuery = $query->get('date_query');
        if (!is_array($dateQuery)) {
            $dateQuery = [];
        }

        $dateQuery[] = [
            'after'     => $range['start'],
            'before'    => $range['end'],
            'inclusive' => true,
        ];

        $query->set('date_query', $dateQuery);
        $query->set(self::QUERY_VAR, $parts + ['vars' => $original]);
    }

    /**
     * Put the Jalali date parts back once the SQL is built. The posts are
     * returned unchanged.
     */
    public function restoreDateVars(mixed $posts, \WP_Query $query): mixed
    {
        $jalali = $query->get(self::QUERY_VAR);

        if (is_array($jalali) && isset($jalali['vars']) && is_array($jalali['vars'])) {
            foreach ($jalali['vars'] as $var => $value) {
                $query->set($var, $value);
            }
        }

        return $posts;
    }

    /**
     * The Jalali year, month and day of the archive being viewed, or null
     * when it is not a Jalali date archive.
     *
     * @return array{year: int, month: ?int, day: ?int}|null
     */
    public static function current(): ?array
    {
        $jalali = get_query_var(self::QUERY_VAR);

        if (!is_array($jalali) || !isset($jalali['year'])) {
            return null;
        }

        return [
            'year'  => (int) $jalali['year'],
            'month' => isset($jalali['month']) ? (int) $jalali['month'] : null,
            'day'   => isset($jalali['day']) ? (int) $jalali['day'] : null,
        ];
    }

    /**
     * Jalali year, month and day from year/monthnum/day or the compact "m"
     * var, or null when the year is not Jalali.
     *
     * @return array{year: int, month: ?int, day: ?int}|null
     */
    private function jalaliParts(\WP_Query $query): ?array
    {
        $m = preg_replace('/\D/', '', (string) $query->get('m'));

        if (is_string($m) && strlen($m) >= 4) {
            $parts = [
                'year'  => (int) substr($m, 0, 4),
                'month' => strlen($m) >= 6 ? (int) substr($m, 4, 2) : null,
                'day'   => strlen($m) >= 8 ? (int) substr($m, 6, 2) : null,
            ];
        } else {
            $month = (int) $query->get('monthnum');
            $day = (int) $query->get('day');

            $parts = [
                'year'  => (int) $query->get('year'),
                'month' => $month > 0 ? $month : null,
                'day'   => $month > 0 && $day > 0 ? $day : null,
            ];
        }

        return JalaliPeriod::isJalaliYear($parts['year']) ? $parts : null;
    }
}
