<?php

namespace PersianKit\Modules\DateConversion;

use PersianKit\Service\Language\ContentLanguage;

defined('ABSPATH') || exit;

/**
 * Jalali dates in post permalinks: /1405/07/09/my-post/ instead of
 * /2026/10/01/my-post/ when the permalink structure has the date.
 *
 * JalaliDateArchive already resolves a Jalali year in a single-post request.
 * This class writes the links, redirects addresses in the other calendar to
 * the current one, and finds posts by an old slug or date under a Jalali date.
 *
 * On multilingual sites only Persian posts get Jalali dates; the others keep
 * Gregorian ones, and their Jalali addresses redirect to them.
 */
class JalaliPermalinks
{
    private const DATE_TAGS = ['%year%', '%monthnum%', '%day%'];

    /** Jalali dates for every post, while redirectUrl() looks up a post's Jalali link. */
    private bool $forceJalali = false;

    /**
     * Redirects and old-slug lookups. These run whichever calendar the links
     * use, so switching the setting either way keeps old links working.
     */
    public function register(): void
    {
        // Before core's redirect_canonical() and wp_old_slug_redirect().
        add_action('template_redirect', [$this, 'redirectToCurrentCalendar'], 9);
        add_filter('old_slug_redirect_post_id', [$this, 'findByOldSlug']);
        add_filter('pre_redirect_guess_404_permalink', [$this, 'guess404Permalink']);
    }

    public function registerJalaliLinks(): void
    {
        add_filter('pre_post_link', [$this, 'filterStructure'], 10, 2);
    }

    /**
     * Put the Jalali date in the structure before core fills in its tags.
     * Core applies pre_post_link to posts only, the editor's sample
     * permalink included.
     */
    public function filterStructure(mixed $structure, mixed $post): mixed
    {
        if (!is_string($structure) || !$post instanceof \WP_Post || !self::hasDateTags($structure)) {
            return $structure;
        }

        if (!$this->forceJalali && !ContentLanguage::postIsPersian($post)) {
            return $structure;
        }

        $jalali = self::jalaliDate((string) $post->post_date);
        if ($jalali === null) {
            return $structure;
        }

        return str_replace(
            self::DATE_TAGS,
            [(string) $jalali['jy'], sprintf('%02d', $jalali['jm']), sprintf('%02d', $jalali['jd'])],
            $structure
        );
    }

    public function redirectToCurrentCalendar(): void
    {
        $requestUri = isset($_SERVER['REQUEST_URI']) ? esc_url_raw(wp_unslash($_SERVER['REQUEST_URI'])) : '';

        $url = $this->redirectUrl($requestUri);
        if ($url !== null && wp_safe_redirect($url, 301)) {
            exit;
        }
    }

    /**
     * Where a request for a post under its date in the other calendar should
     * go: the request path with that link swapped for the post's permalink.
     * The rest of the path (/2/, /feed/, /embed/, comment pages, attachments)
     * and the query string are kept. Null when no redirect is needed.
     */
    public function redirectUrl(string $requestUri): ?string
    {
        if (is_preview() || !self::hasDateTags((string) get_option('permalink_structure'))) {
            return null;
        }

        $post = $this->requestedPost();
        if ($post === null) {
            return null;
        }

        $permalink = get_permalink($post);
        if ($permalink === '') {
            return null;
        }

        $jalaliLink = $this->permalinkIn($post, true);
        $otherLink = $jalaliLink === $permalink ? $this->permalinkIn($post, false) : $jalaliLink;
        if ($otherLink === $permalink) {
            return null;
        }

        $path = self::urlPart($requestUri, PHP_URL_PATH);
        $query = self::urlPart($requestUri, PHP_URL_QUERY);

        // Compared decoded: slugs may be stored raw or encoded, and browsers
        // send them encoded.
        $decode = static fn (string $segment): string => mb_strtolower(rawurldecode($segment));
        $requested = explode('/', ltrim($path, '/'));
        $expected = explode('/', trim(self::urlPart($otherLink, PHP_URL_PATH), '/'));
        $head = array_slice($requested, 0, count($expected));
        if (array_map($decode, $head) !== array_map($decode, $expected)) {
            return null;
        }

        $rest = implode('/', array_slice($requested, count($expected)));
        $url = $rest === '' ? $permalink : trailingslashit($permalink) . $rest;

        return $query === '' ? $url : $url . '?' . $query;
    }

    /**
     * Core looks old slugs and dates up with YEAR(), MONTH() and
     * DAYOFMONTH(), which never match a Jalali date. Look them up again
     * within the Jalali date's Gregorian range.
     */
    public function findByOldSlug(mixed $id): mixed
    {
        if (!empty($id)) {
            return $id;
        }

        $range = self::requestedRange();
        if ($range === null) {
            return $id;
        }

        global $wpdb;

        $postType = get_query_var('post_type');
        $postType = is_string($postType) && $postType !== '' ? $postType : 'post';
        $name = (string) get_query_var('name');
        $startDay = substr($range['start'], 0, 10);
        $endDay = substr($range['end'], 0, 10);

        $cacheKey = 'old_slug:' . md5(implode('|', [$postType, $name, $range['start'], $range['end']])) . ':' . wp_cache_get_last_changed('posts');
        $cached = wp_cache_get($cacheKey, 'persian_kit');
        if ($cached !== false) {
            return (int) $cached;
        }

        // The queries core's _find_post_by_old_slug() and _find_post_by_old_date()
        // run, on a date range; the result is cached above until posts change.
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
        $found = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT post_id FROM {$wpdb->postmeta}, {$wpdb->posts}
            WHERE ID = post_id AND post_type = %s
              AND meta_key = '_wp_old_slug' AND meta_value = %s
              AND post_date >= %s AND post_date <= %s",
            $postType,
            $name,
            $range['start'],
            $range['end']
        ));

        if ($found === 0) {
            $found = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} AS pm_date, {$wpdb->posts}
                WHERE ID = post_id AND post_type = %s AND post_name = %s
                  AND meta_key = '_wp_old_date' AND meta_value >= %s AND meta_value <= %s",
                $postType,
                $name,
                $startDay,
                $endDay
            ));
        }

        if ($found === 0) {
            $found = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}, {$wpdb->postmeta} AS pm_slug, {$wpdb->postmeta} AS pm_date
                WHERE ID = pm_slug.post_id AND ID = pm_date.post_id AND post_type = %s
                  AND pm_slug.meta_key = '_wp_old_slug' AND pm_slug.meta_value = %s
                  AND pm_date.meta_key = '_wp_old_date' AND pm_date.meta_value >= %s AND pm_date.meta_value <= %s",
                $postType,
                $name,
                $startDay,
                $endDay
            ));
        }
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

        wp_cache_set($cacheKey, $found, 'persian_kit');

        return $found;
    }

    /**
     * Core guesses a cut-off address (/1405/07/09/my-po/) with YEAR(),
     * MONTH() and DAYOFMONTH(), which never match a Jalali date. Guess the
     * same way within the Jalali date's Gregorian range. False when nothing
     * matches, so core does not run its own query.
     */
    public function guess404Permalink(mixed $pre): mixed
    {
        $name = get_query_var('name');
        if ($pre !== null || !is_string($name) || $name === '') {
            return $pre;
        }

        $range = self::requestedRange();
        if ($range === null) {
            return $pre;
        }

        global $wpdb;

        $statuses = array_values(array_filter(get_post_stati(), 'is_post_status_viewable'));
        $postTypes = array_values(array_filter(get_post_types(['exclude_from_search' => false]), 'is_post_type_viewable'));

        $requestedTypes = get_query_var('post_type');
        if (!empty($requestedTypes)) {
            $postTypes = array_values(array_intersect((array) $requestedTypes, $postTypes));
        }
        if ($statuses === [] || $postTypes === []) {
            return false;
        }

        // Without the trailing %, the escaped pattern matches the slug exactly.
        $strict = (bool) apply_filters('strict_redirect_guess_404_permalink', false);
        $namePattern = $wpdb->esc_like($name) . ($strict ? '' : '%');

        // Core's query, on a date range. Runs only on a 404.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $postId = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts}
            WHERE post_name LIKE %s
              AND post_type IN (" . implode(',', array_fill(0, count($postTypes), '%s')) . ")
              AND post_date >= %s AND post_date <= %s
              AND post_status IN (" . implode(',', array_fill(0, count($statuses), '%s')) . ")",
            array_merge([$namePattern], $postTypes, [$range['start'], $range['end']], $statuses)
        ));

        if ($postId === 0) {
            return false;
        }

        $feed = get_query_var('feed');
        if (is_string($feed) && $feed !== '') {
            return get_post_comments_feed_link($postId, $feed);
        }

        $page = (int) get_query_var('page');
        if ($page > 1) {
            return trailingslashit((string) get_permalink($postId)) . user_trailingslashit((string) $page, 'single_paged');
        }

        return get_permalink($postId);
    }

    /**
     * The post requested, or the post an attachment request sits under.
     */
    private function requestedPost(): ?\WP_Post
    {
        if (!is_singular()) {
            return null;
        }

        $post = get_queried_object();
        if ($post instanceof \WP_Post && $post->post_type === 'attachment' && $post->post_parent > 0) {
            $post = get_post($post->post_parent);
        }

        return $post instanceof \WP_Post && $post->post_type === 'post' ? $post : null;
    }

    /**
     * The post's permalink with Jalali or Gregorian dates, whichever the
     * links currently use. Jalali here means Jalali whatever the post's
     * language.
     */
    private function permalinkIn(\WP_Post $post, bool $jalali): string
    {
        $callback = [$this, 'filterStructure'];
        $priority = has_filter('pre_post_link', $callback);

        if ($priority !== false) {
            remove_filter('pre_post_link', $callback, $priority);
        }
        if ($jalali) {
            add_filter('pre_post_link', $callback, 10, 2);
            $this->forceJalali = true;
        }

        try {
            $link = get_permalink($post);
        } finally {
            if ($jalali) {
                $this->forceJalali = false;
                remove_filter('pre_post_link', $callback, 10);
            }
        }
        if ($priority !== false) {
            add_filter('pre_post_link', $callback, $priority, 2);
        }

        return $link;
    }

    /**
     * Gregorian range of the Jalali date in the request, or null when the
     * request has no Jalali year.
     *
     * @return array{start: string, end: string}|null
     */
    private static function requestedRange(): ?array
    {
        $year = (int) get_query_var('year');
        if (!JalaliPeriod::isJalaliYear($year)) {
            return null;
        }

        $month = (int) get_query_var('monthnum');
        $day = (int) get_query_var('day');

        return JalaliPeriod::range($year, $month > 0 ? $month : null, $month > 0 && $day > 0 ? $day : null);
    }

    /**
     * parse_url() mangles raw UTF-8 bytes, as in a stored Persian slug, so
     * they are percent-encoded first.
     */
    private static function urlPart(string $url, int $component): string
    {
        $encoded = (string) preg_replace_callback(
            '/[^\x00-\x7F]+/',
            static fn (array $match): string => rawurlencode($match[0]),
            $url
        );

        return (string) wp_parse_url($encoded, $component);
    }

    private static function hasDateTags(string $structure): bool
    {
        foreach (self::DATE_TAGS as $tag) {
            if (str_contains($structure, $tag)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{jy: int, jm: int, jd: int}|null
     */
    private static function jalaliDate(string $postDate): ?array
    {
        if (!preg_match('/^(?!0000)\d{4}-\d{2}-\d{2}/', $postDate)) {
            return null;
        }

        return JalaliPeriod::fromGregorian(new \DateTimeImmutable(substr($postDate, 0, 10) . ' 12:00:00', wp_timezone()));
    }
}
