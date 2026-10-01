<?php

namespace PersianKit\Modules\DateConversion;

defined('ABSPATH') || exit;

/**
 * Lists Jalali months, years or days in wp_get_archives(), which the Archives
 * widget and block also use, linked to the Jalali archive pages.
 *
 * WordPress has no filter for the whole list, so the call is followed through
 * its filters: getarchives_where and getarchives_join give the final SQL and
 * arguments, and on core's first get_archives_link() the whole Jalali list is
 * returned, built with get_archives_link() itself so markup, format, before,
 * after and other plugins' filters still apply. Core's remaining calls, one
 * per Gregorian period, return ''.
 */
class JalaliArchiveList
{
    private const TYPES = ['monthly', 'yearly', 'daily'];

    private ?string $where = null;

    /** @var array<string, mixed>|null */
    private ?array $args = null;

    private string $list = '';

    /** Core get_archives_link() calls still to answer for the current list. */
    private int $pendingCalls = 0;

    private bool $building = false;

    public function register(): void
    {
        add_filter('getarchives_where', [$this, 'captureWhere'], PHP_INT_MAX, 2);
        add_filter('getarchives_join', [$this, 'captureJoin'], PHP_INT_MAX, 2);
        add_filter('get_archives_link', [$this, 'filterArchivesLink'], PHP_INT_MAX);
    }

    public function captureWhere(mixed $where, mixed $args = []): mixed
    {
        $this->where = null;
        $this->args = null;
        $this->list = '';
        $this->pendingCalls = 0;

        if (is_string($where) && is_array($args) && in_array($args['type'] ?? '', self::TYPES, true)) {
            $this->where = $where;
            $this->args = $args;
        }

        return $where;
    }

    public function captureJoin(mixed $join, mixed $args = []): mixed
    {
        if ($this->where === null || $this->args === null || !is_string($join) || $args !== $this->args) {
            return $join;
        }

        $this->prepare($this->days($join, $this->where), $this->args);
        $this->where = null;

        return $join;
    }

    public function filterArchivesLink(mixed $linkHtml): mixed
    {
        if ($this->building || $this->pendingCalls === 0) {
            return $linkHtml;
        }

        $this->pendingCalls--;
        $list = $this->list;
        $this->list = '';

        return $list;
    }

    /**
     * @param array<string, int>   $days Post counts by site-local Y-m-d.
     * @param array<string, mixed> $args wp_get_archives() arguments as core parsed them.
     */
    private function prepare(array $days, array $args): void
    {
        $type = (string) $args['type'];
        $limit = (int) preg_replace('/\D/', '', (string) ($args['limit'] ?? ''));
        $descending = strtoupper((string) ($args['order'] ?? 'DESC')) !== 'ASC';

        $periods = [];
        $gregorianPeriods = [];

        foreach ($days as $day => $count) {
            $gregorianPeriods[$this->periodKey($type, (int) substr($day, 0, 4), (int) substr($day, 5, 2), (int) substr($day, 8, 2))] = true;

            $jalali = JalaliPeriod::fromGregorian(new \DateTimeImmutable($day . ' 12:00:00', wp_timezone()));
            $key = $this->periodKey($type, $jalali['jy'], $jalali['jm'], $jalali['jd']);
            $periods[$key] = ($periods[$key] ?? 0) + $count;
        }

        if ($periods === []) {
            return;
        }

        $descending ? krsort($periods, SORT_STRING) : ksort($periods, SORT_STRING);

        if ($limit > 0) {
            $periods = array_slice($periods, 0, $limit, true);
        }

        $this->list = $this->render($periods, $type, $args);
        $this->pendingCalls = $limit > 0 ? min($limit, count($gregorianPeriods)) : count($gregorianPeriods);
    }

    /**
     * @param array<string, int>   $periods Post counts by periodKey().
     * @param array<string, mixed> $args
     */
    private function render(array $periods, string $type, array $args): string
    {
        $postType = (string) ($args['post_type'] ?? 'post');
        $output = '';

        $this->building = true;

        try {
            foreach ($periods as $key => $count) {
                $parts = array_map('intval', explode('-', (string) $key));
                [$jy, $jm, $jd] = $parts + [0, 1, 1];

                $date = JalaliPeriod::toDateTime($jy, $jm, $jd);
                if ($date === null) {
                    continue;
                }

                $url = match ($type) {
                    'yearly' => get_year_link($jy),
                    'daily'  => get_day_link($jy, $jm, $jd),
                    default  => get_month_link($jy, $jm),
                };

                if ($postType !== 'post') {
                    $url = add_query_arg('post_type', $postType, $url);
                }

                $text = JalaliFormatter::formatDateTime(match ($type) {
                    'yearly' => 'Y',
                    'daily'  => (string) get_option('date_format'),
                    default  => 'F Y',
                }, $date);

                $after = (string) ($args['after'] ?? '');
                if (!empty($args['show_post_count'])) {
                    $after = '&nbsp;(' . $count . ')' . $after;
                }

                $output .= get_archives_link(
                    $url,
                    $text,
                    (string) ($args['format'] ?? 'html'),
                    (string) ($args['before'] ?? ''),
                    $after,
                    $this->isSelected($type, $args, $jy, $jm, $jd)
                );
            }
        } finally {
            $this->building = false;
        }

        return $output;
    }

    /**
     * Like core, the entry for the archive being viewed: the year, monthnum
     * and day arguments default to the query vars, which hold the Jalali
     * parts on a Jalali archive.
     *
     * @param array<string, mixed> $args
     */
    private function isSelected(string $type, array $args, int $jy, int $jm, int $jd): bool
    {
        if (!is_archive() || (int) ($args['year'] ?? 0) !== $jy) {
            return false;
        }

        return match ($type) {
            'yearly' => true,
            'daily'  => (int) ($args['monthnum'] ?? 0) === $jm && (int) ($args['day'] ?? 0) === $jd,
            default  => (int) ($args['monthnum'] ?? 0) === $jm,
        };
    }

    /**
     * A sortable key for the year, month or day: "1405", "1405-07" or "1405-07-09".
     */
    private function periodKey(string $type, int $year, int $month, int $day): string
    {
        return match ($type) {
            'yearly' => sprintf('%04d', $year),
            'daily'  => sprintf('%04d-%02d-%02d', $year, $month, $day),
            default  => sprintf('%04d-%02d', $year, $month),
        };
    }

    /**
     * Post counts by site-local day, for the same posts core's query lists.
     *
     * @return array<string, int>
     */
    private function days(string $join, string $where): array
    {
        global $wpdb;

        $query = "SELECT DATE(post_date) AS post_day, COUNT(ID) AS posts FROM {$wpdb->posts} $join $where GROUP BY DATE(post_date)";
        $cacheKey = 'archive_days:' . md5($query) . ':' . wp_cache_get_last_changed('posts');

        $cached = wp_cache_get($cacheKey, 'persian_kit');
        if (is_array($cached)) {
            return $cached;
        }

        // The JOIN and WHERE are core's own wp_get_archives() clauses, already
        // prepared and filtered; the result is cached above until posts change.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results($query);

        $days = [];
        foreach ((array) $rows as $row) {
            if (is_object($row) && isset($row->post_day) && preg_match('/^(?!0000)\d{4}-\d{2}-\d{2}$/', (string) $row->post_day)) {
                $days[(string) $row->post_day] = (int) $row->posts;
            }
        }

        wp_cache_set($cacheKey, $days, 'persian_kit');

        return $days;
    }
}
