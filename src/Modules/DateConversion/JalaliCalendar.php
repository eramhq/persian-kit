<?php

namespace PersianKit\Modules\DateConversion;

use PersianKit\Service\Language\ContentLanguage;

defined('ABSPATH') || exit;

/**
 * Replaces get_calendar(), which the Calendar widget and block also use, with
 * a Jalali month. The markup copies core's (table#wp-calendar, td#today,
 * nav.wp-calendar-nav), so theme and block styles still apply.
 */
class JalaliCalendar
{
    public function register(): void
    {
        add_filter('get_calendar', [$this, 'filterCalendar'], 10, 2);
    }

    /**
     * @param mixed $args get_calendar() arguments: initial, display, post_type.
     */
    public function filterCalendar(mixed $output, mixed $args = []): mixed
    {
        // Core caches '' when there are no posts at all. Pages not in
        // Persian keep core's Gregorian month.
        if (!is_string($output) || $output === '' || !ContentLanguage::displaysPersian()) {
            return $output;
        }

        $args = is_array($args) ? $args : [];
        $postType = isset($args['post_type']) && is_string($args['post_type']) ? $args['post_type'] : 'post';
        [$jy, $jm] = $this->month();

        $calendar = $this->render($jy, $jm, $postType, !isset($args['initial']) || (bool) $args['initial']);

        return $calendar ?? $output;
    }

    /**
     * The Jalali month to show, from the same globals core reads: a Jalali
     * archive's year and month, or the Jalali month in which a Gregorian
     * month (an archive, or the Calendar block's month and year) starts, or
     * the current month.
     *
     * @return array{int, int}
     */
    private function month(): array
    {
        global $m, $monthnum, $year;

        $queriedYear = is_numeric($year) ? (int) $year : 0;
        $queriedMonth = is_numeric($monthnum) ? (int) $monthnum : 0;
        $compact = preg_replace('/\D/', '', (string) $m);

        if ($queriedYear > 0 && $queriedMonth > 0) {
            return $this->resolve($queriedYear, $queriedMonth);
        }

        if (is_string($compact) && strlen($compact) >= 4) {
            return $this->resolve((int) substr($compact, 0, 4), strlen($compact) >= 6 ? (int) substr($compact, 4, 2) : 1);
        }

        $today = JalaliPeriod::fromGregorian(current_datetime());

        return [$today['jy'], $today['jm']];
    }

    /**
     * @return array{int, int}
     */
    private function resolve(int $year, int $month): array
    {
        if (JalaliPeriod::isJalaliYear($year)) {
            return [$year, $month];
        }

        if (!checkdate($month, 1, $year)) {
            $today = JalaliPeriod::fromGregorian(current_datetime());

            return [$today['jy'], $today['jm']];
        }

        $jalali = JalaliPeriod::fromGregorian(new \DateTimeImmutable(sprintf('%04d-%02d-01 12:00:00', $year, $month), wp_timezone()));

        return [$jalali['jy'], $jalali['jm']];
    }

    private function render(int $jy, int $jm, string $postType, bool $initial): ?string
    {
        global $wp_locale;

        $firstDay = JalaliPeriod::toDateTime($jy, $jm, 1);
        if ($firstDay === null) {
            return null;
        }

        $data = $this->monthData($jy, $jm, $postType);
        if ($data === null) {
            return null;
        }

        $weekBegins = (int) get_option('start_of_week');
        $today = JalaliPeriod::fromGregorian(current_datetime());
        $dateFormat = (string) get_option('date_format');

        $output = '<table id="wp-calendar" class="wp-calendar-table">
	<caption>' . esc_html(JalaliFormatter::formatDateTime('F Y', $firstDay)) . '</caption>
	<thead>
	<tr>';

        for ($index = 0; $index <= 6; $index++) {
            $weekday = $wp_locale->get_weekday(($index + $weekBegins) % 7);
            $dayName = $initial ? $wp_locale->get_weekday_initial($weekday) : $wp_locale->get_weekday_abbrev($weekday);
            $output .= "\n\t\t<th scope=\"col\" aria-label=\"" . esc_attr($weekday) . '">' . esc_html($dayName) . '</th>';
        }

        $output .= '
	</tr>
	</thead>
	<tbody>
	<tr>';

        $pad = $this->weekColumn($firstDay, $weekBegins);
        if ($pad > 0) {
            $output .= "\n\t\t" . '<td colspan="' . esc_attr((string) $pad) . '" class="pad">&nbsp;</td>';
        }

        $daysInMonth = JalaliPeriod::daysInMonth($jy, $jm);
        $column = $pad;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            if ($column === 7) {
                $output .= "\n\t</tr>\n\t<tr>\n\t\t";
                $column = 0;
            }

            $date = $firstDay->modify('+' . ($day - 1) . ' days');
            $isToday = $today['jy'] === $jy && $today['jm'] === $jm && $today['jd'] === $day;
            $output .= $isToday ? '<td id="today">' : '<td>';

            $number = esc_html(JalaliFormatter::formatDateTime('j', $date));

            if (in_array($day, $data['days'], true)) {
                /* translators: Post calendar label. %s: Date. */
                $label = sprintf(__('Posts published on %s', 'persian-kit'), JalaliFormatter::formatDateTime($dateFormat, $date));
                $output .= sprintf(
                    '<a href="%s" aria-label="%s">%s</a>',
                    esc_url(get_day_link($jy, $jm, $day)),
                    esc_attr($label),
                    $number
                );
            } else {
                $output .= $number;
            }

            $output .= '</td>';
            $column++;
        }

        if ($column < 7) {
            $output .= "\n\t\t" . '<td class="pad" colspan="' . esc_attr((string) (7 - $column)) . '">&nbsp;</td>';
        }

        $output .= "\n\t</tr>\n\t</tbody>";
        $output .= "\n\t</table>";
        $output .= '<nav aria-label="' . esc_attr__('Previous and next months', 'persian-kit') . '" class="wp-calendar-nav">';
        $output .= "\n\t\t" . $this->navLink('prev', $data['previous'], $postType);
        $output .= "\n\t\t" . '<span class="pad">&nbsp;</span>';
        $output .= "\n\t\t" . $this->navLink('next', $data['next'], $postType);
        $output .= '
	</nav>';

        return $output;
    }

    /**
     * Column of a day in a week starting on $weekBegins (0 = Sunday).
     */
    private function weekColumn(\DateTimeImmutable $date, int $weekBegins): int
    {
        return ((int) $date->format('w') - $weekBegins + 7) % 7;
    }

    /**
     * @param array{int, int}|null $month Jalali year and month, or null for none.
     */
    private function navLink(string $direction, ?array $month, string $postType): string
    {
        $class = 'wp-calendar-nav-' . $direction;

        if ($month === null) {
            return '<span class="' . $class . '">&nbsp;</span>';
        }

        $date = JalaliPeriod::toDateTime($month[0], $month[1], 1);
        if ($date === null) {
            return '<span class="' . $class . '">&nbsp;</span>';
        }

        $url = get_month_link($month[0], $month[1]);
        if ($postType !== 'post') {
            $url = add_query_arg('post_type', $postType, $url);
        }

        $name = esc_html(JalaliFormatter::formatDateTime('F', $date));
        $text = $direction === 'prev' ? '&laquo; ' . $name : $name . ' &raquo;';

        return sprintf('<span class="%s"><a href="%s">%s</a></span>', $class, esc_url($url), $text);
    }

    /**
     * Days of the Jalali month with posts, and the nearest earlier and later
     * months with posts. Cached until posts change.
     *
     * @return array{days: list<int>, previous: array{int, int}|null, next: array{int, int}|null}|null
     */
    private function monthData(int $jy, int $jm, string $postType): ?array
    {
        global $wpdb;

        $range = JalaliPeriod::range($jy, $jm);
        if ($range === null) {
            return null;
        }

        // On multilingual sites, only posts in the page's language, as the
        // archives the links lead to.
        $language = ContentLanguage::postsInCurrentLanguage([$postType]);
        $inLanguage = $language === null ? '' : " AND {$language}";

        $cacheKey = sprintf('calendar:%s:%04d%02d:%s', $postType, $jy, $jm, wp_cache_get_last_changed('posts'))
            . ($language === null ? '' : ':' . md5($language));
        $cached = wp_cache_get($cacheKey, 'persian_kit');
        if (is_array($cached)) {
            return $cached;
        }

        // The same three queries core's get_calendar() runs, on the Jalali
        // month's Gregorian range; the result is cached above until posts change.
        // $inLanguage is built with prepare() in ContentLanguage.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
        $postDays = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT DATE(post_date)
            FROM {$wpdb->posts}
            WHERE post_type = %s AND post_status = 'publish'
              AND post_date >= %s AND post_date <= %s{$inLanguage}",
            $postType,
            $range['start'],
            $range['end']
        ));

        $previous = $wpdb->get_var($wpdb->prepare(
            "SELECT post_date
            FROM {$wpdb->posts}
            WHERE post_date < %s
              AND post_type = %s AND post_status = 'publish'{$inLanguage}
            ORDER BY post_date DESC
            LIMIT 1",
            $range['start'],
            $postType
        ));

        $next = $wpdb->get_var($wpdb->prepare(
            "SELECT post_date
            FROM {$wpdb->posts}
            WHERE post_date > %s
              AND post_type = %s AND post_status = 'publish'{$inLanguage}
            ORDER BY post_date ASC
            LIMIT 1",
            $range['end'],
            $postType
        ));
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

        $days = [];
        foreach ((array) $postDays as $postDay) {
            $jalali = $this->jalaliOf((string) $postDay);
            if ($jalali !== null) {
                $days[] = $jalali['jd'];
            }
        }

        $data = [
            'days'     => array_values(array_unique($days)),
            'previous' => $this->monthOf($previous),
            'next'     => $this->monthOf($next),
        ];

        wp_cache_set($cacheKey, $data, 'persian_kit');

        return $data;
    }

    /**
     * @return array{int, int}|null
     */
    private function monthOf(mixed $postDate): ?array
    {
        $jalali = is_string($postDate) ? $this->jalaliOf($postDate) : null;

        return $jalali === null ? null : [$jalali['jy'], $jalali['jm']];
    }

    /**
     * @return array{jy: int, jm: int, jd: int}|null
     */
    private function jalaliOf(string $mysqlDate): ?array
    {
        if (!preg_match('/^(?!0000)\d{4}-\d{2}-\d{2}/', $mysqlDate)) {
            return null;
        }

        return JalaliPeriod::fromGregorian(new \DateTimeImmutable(substr($mysqlDate, 0, 10) . ' 12:00:00', wp_timezone()));
    }
}
