<?php

namespace PersianKit\Modules\DateConversion;

defined('ABSPATH') || exit;

class DateFilters
{
    private bool $globalConversion;
    private string $defaultDateFormat;
    private string $defaultTimeFormat;

    private static bool $inFilter = false;

    public function __construct(bool $globalConversion)
    {
        $this->globalConversion = $globalConversion;
        $this->defaultDateFormat = get_option('date_format');
        $this->defaultTimeFormat = get_option('time_format');
    }

    /**
     * Tier 1: Safe, template-level filters at default priority.
     *
     * These re-format from the raw stored date, ignoring the pre-formatted argument.
     */
    public function registerTier1(): void
    {
        add_filter('get_the_date', [$this, 'filterPostDate'], 10, 3);
        add_filter('the_date', [$this, 'filterTheDate'], 10, 4);
        add_filter('get_the_time', [$this, 'filterPostTime'], 10, 3);
        add_filter('get_the_modified_date', [$this, 'filterModifiedDate'], 10, 3);
        add_filter('get_the_modified_time', [$this, 'filterModifiedTime'], 10, 3);
        add_filter('get_comment_date', [$this, 'filterCommentDate'], 10, 3);
        add_filter('get_comment_time', [$this, 'filterCommentTime'], 10, 5);
        add_filter('get_post_time', [$this, 'filterGetPostTime'], 10, 3);
        add_filter('render_block_core/post-date', [$this, 'filterPostDateBlock'], 10, 3);
        add_filter('render_block_core/latest-comments', [$this, 'filterLatestCommentsBlock'], 10, 3);
    }

    /**
     * Tier 2: Opt-in wp_date hook for global conversion.
     */
    public function registerTier2(): void
    {
        if (!$this->globalConversion) {
            return;
        }

        add_filter('wp_date', [$this, 'filterWpDate'], 10, 4);
    }

    /**
     * Admin-specific filters (admin bar Jalali clock).
     */
    public function registerAdminFilters(): void
    {
        add_action('admin_bar_menu', [$this, 'addAdminBarClock'], 7);
        add_action('wp_dashboard_setup', [$this, 'replaceDashboardActivityWidget'], 100);
    }

    // ── Tier 1 callbacks ──────────────────────────────────────────────

    public function filterPostDate(string $date, string $format, ?object $post = null): string
    {
        if (!$post || DateDisplayGuard::shouldBypass($format)) {
            return $date;
        }

        return $this->formatStored($format ?: $this->defaultDateFormat, $post->post_date ?? null, $post->post_date_gmt ?? null, $date);
    }

    public function filterTheDate(string $date, string $format, string $before, string $after): string
    {
        // the_date() passes '' for every post after the first one of a day.
        if ($date === '') {
            return $date;
        }

        $post = get_post();
        if (!$post || DateDisplayGuard::shouldBypass($format)) {
            return $date;
        }

        $formatted = JalaliFormatter::fromLocalMysql($format ?: $this->defaultDateFormat, $post->post_date ?? null);

        return $formatted === null ? $date : $before . $formatted . $after;
    }

    public function filterPostTime(string $time, string $format, ?object $post = null): string
    {
        if (!$post || DateDisplayGuard::shouldBypass($format)) {
            return $time;
        }

        return $this->formatStored($format ?: $this->defaultTimeFormat, $post->post_date ?? null, $post->post_date_gmt ?? null, $time);
    }

    public function filterModifiedDate(string $date, string $format, ?object $post = null): string
    {
        if (!$post || DateDisplayGuard::shouldBypass($format)) {
            return $date;
        }

        return $this->formatStored($format ?: $this->defaultDateFormat, $post->post_modified ?? null, $post->post_modified_gmt ?? null, $date);
    }

    public function filterModifiedTime(string $time, string $format, ?object $post = null): string
    {
        if (!$post || DateDisplayGuard::shouldBypass($format)) {
            return $time;
        }

        return $this->formatStored($format ?: $this->defaultTimeFormat, $post->post_modified ?? null, $post->post_modified_gmt ?? null, $time);
    }

    public function filterCommentDate(string $date, string $format, ?object $comment = null): string
    {
        if (!$comment || DateDisplayGuard::shouldBypass($format)) {
            return $date;
        }

        return $this->formatStored($format ?: $this->defaultDateFormat, $comment->comment_date ?? null, $comment->comment_date_gmt ?? null, $date);
    }

    public function filterCommentTime(string $time, string $format, bool $gmt, bool $translate, ?object $comment = null): string
    {
        if (!$comment || DateDisplayGuard::shouldBypass($format)) {
            return $time;
        }

        $format = $format ?: $this->defaultTimeFormat;

        if ($gmt) {
            return JalaliFormatter::fromGmtMysql($format, $comment->comment_date_gmt ?? null, new \DateTimeZone('UTC')) ?? $time;
        }

        return $this->formatStored($format, $comment->comment_date ?? null, $comment->comment_date_gmt ?? null, $time);
    }

    /**
     * Core's get_post_time filter does not pass the post, so the global post is
     * used only when $time is that post's date in $format. Otherwise the call was
     * for another post and is left alone.
     */
    public function filterGetPostTime(mixed $time, string $format, bool $gmt): mixed
    {
        if (!is_string($time) || $time === '' || DateDisplayGuard::shouldBypass($format)) {
            return $time;
        }

        $post = get_post();
        $dateTime = $post ? get_post_datetime($post, 'date', $gmt ? 'gmt' : 'local') : false;
        if (!$dateTime instanceof \DateTimeInterface) {
            return $time;
        }

        if ($gmt) {
            $dateTime = \DateTimeImmutable::createFromInterface($dateTime)->setTimezone(new \DateTimeZone('UTC'));
        }

        if (!$this->matchesCoreOutput($time, $format, $dateTime)) {
            return $time;
        }

        return JalaliFormatter::formatDateTime($format, $dateTime);
    }

    public function filterPostDateBlock(string $blockContent, array $block, ?object $instance = null): string
    {
        if ($this->shouldSkipFrontendBlockConversion()) {
            return $blockContent;
        }

        $attrs = is_array($block['attrs'] ?? null) ? $block['attrs'] : [];
        $format = $attrs['format'] ?? $this->defaultDateFormat;

        if ($format === 'human-diff' || DateDisplayGuard::shouldBypass($format)) {
            return $blockContent;
        }

        return $this->replaceRenderedTimeText(
            $blockContent,
            function (string $datetime, string $innerHtml) use ($format): string {
                $formattedDate = JalaliFormatter::format($format, $datetime);

                return $this->replaceLinkedTimeText($innerHtml, $formattedDate);
            },
            true
        );
    }

    public function filterLatestCommentsBlock(string $blockContent, array $block, ?object $instance = null): string
    {
        if ($this->shouldSkipFrontendBlockConversion() || DateDisplayGuard::shouldBypass($this->defaultDateFormat)) {
            return $blockContent;
        }

        return $this->replaceRenderedTimeText(
            $blockContent,
            fn (string $datetime, string $innerHtml): string => JalaliFormatter::format($this->defaultDateFormat, $datetime)
        );
    }

    // ── Tier 2 callback ───────────────────────────────────────────────

    public function filterWpDate(string $date, string $format, int $timestamp, ?\DateTimeZone $timezone = null): string
    {
        if (self::$inFilter || DateDisplayGuard::shouldBypass($format)) {
            return $date;
        }

        self::$inFilter = true;
        try {
            $result = JalaliFormatter::format($format, $timestamp, $timezone);
        } finally {
            self::$inFilter = false;
        }

        return $result;
    }

    // ── Admin filters ─────────────────────────────────────────────────

    public function addAdminBarClock(object $adminBar): void
    {
        $jalaliDate = JalaliFormatter::format('l j F Y');

        $adminBar->add_node([
            'id'     => 'persian-kit-date',
            'title'  => $jalaliDate,
            'parent' => 'top-secondary',
            'meta'   => ['class' => 'persian-kit-admin-date'],
        ]);
    }

    public function replaceDashboardActivityWidget(): void
    {
        if (!function_exists('wp_add_dashboard_widget') || !function_exists('wp_dashboard_site_activity')) {
            return;
        }

        remove_meta_box('dashboard_activity', 'dashboard', 'normal');
        wp_add_dashboard_widget(
            'dashboard_activity',
            __('Activity'),
            [$this, 'renderDashboardActivityWidget'],
            null,
            null,
            'normal',
            'default'
        );
    }

    public function renderDashboardActivityWidget(): void
    {
        add_filter('date_i18n', [$this, 'filterDashboardDateI18n'], 10, 4);

        try {
            wp_dashboard_site_activity();
        } finally {
            remove_filter('date_i18n', [$this, 'filterDashboardDateI18n'], 10);
        }
    }

    public function filterDashboardDateI18n(string $date, string $format, int $timestamp, bool $gmt = false): string
    {
        if (self::$inFilter || DateDisplayGuard::shouldBypass($format)) {
            return $date;
        }

        self::$inFilter = true;
        try {
            $result = JalaliFormatter::fromOffsetTimestamp($format, $timestamp, $gmt);
        } finally {
            self::$inFilter = false;
        }

        return $result;
    }

    /**
     * Format a stored local/GMT MySQL datetime pair, preferring the local value
     * as core does. Falls back to core's output when neither parses.
     */
    private function formatStored(string $format, ?string $local, ?string $gmt, string $fallback): string
    {
        return JalaliFormatter::fromLocalMysql($format, $local)
            ?? JalaliFormatter::fromGmtMysql($format, $gmt)
            ?? $fallback;
    }

    private function matchesCoreOutput(string $time, string $format, \DateTimeInterface $dateTime): bool
    {
        if ($dateTime->format($format) === $time) {
            return true;
        }

        // Translated output ($translate = true) comes from wp_date(); keep our own
        // wp_date filter out of the comparison.
        $previous = self::$inFilter;
        self::$inFilter = true;
        try {
            return wp_date($format, $dateTime->getTimestamp(), $dateTime->getTimezone()) === $time;
        } finally {
            self::$inFilter = $previous;
        }
    }

    private function shouldSkipFrontendBlockConversion(): bool
    {
        return function_exists('is_admin') && is_admin();
    }

    private function replaceRenderedTimeText(string $markup, callable $formatter, bool $firstOnly = false): string
    {
        // Keep the rewrite scoped to rendered TIME tags so visible date text changes
        // without affecting machine-facing attributes like datetime.
        $result = preg_replace_callback(
            '/(?P<open><time\b[^>]*>)(?P<inner>.*?)(?P<close><\/time>)/is',
            function (array $matches) use ($formatter): string {
                if (!preg_match('/\bdatetime=(["\'])(?P<datetime>.*?)\1/i', $matches['open'], $datetimeMatches)) {
                    return $matches[0];
                }

                $datetime = html_entity_decode($datetimeMatches['datetime'], ENT_QUOTES, 'UTF-8');
                $replacement = $formatter($datetime, $matches['inner']);

                return $matches['open'] . $replacement . $matches['close'];
            },
            $markup,
            $firstOnly ? 1 : -1
        );

        return is_string($result) ? $result : $markup;
    }

    private function replaceLinkedTimeText(string $innerHtml, string $replacement): string
    {
        if (!preg_match('/^(?P<prefix>\s*)(?P<open><a\b[^>]*>)(?P<text>.*?)(?P<close><\/a>)(?P<suffix>\s*)$/is', $innerHtml, $matches)) {
            return $replacement;
        }

        return $matches['prefix'] . $matches['open'] . $replacement . $matches['close'] . $matches['suffix'];
    }
}
