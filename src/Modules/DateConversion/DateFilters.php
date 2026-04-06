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
        if (!$post || $this->shouldBypassDisplayConversion($format)) {
            return $date;
        }

        $format = $format ?: $this->defaultDateFormat;

        return JalaliFormatter::format($format, $post->post_date_gmt ?: $post->post_date);
    }

    public function filterTheDate(string $date, string $format, string $before, string $after): string
    {
        $post = get_post();
        if (!$post || $this->shouldBypassDisplayConversion($format)) {
            return $date;
        }

        $format = $format ?: $this->defaultDateFormat;

        return $before . JalaliFormatter::format($format, $post->post_date_gmt ?: $post->post_date) . $after;
    }

    public function filterPostTime(string $time, string $format, ?object $post = null): string
    {
        if (!$post || $this->shouldBypassDisplayConversion($format)) {
            return $time;
        }

        $format = $format ?: $this->defaultTimeFormat;

        return JalaliFormatter::format($format, $post->post_date_gmt ?: $post->post_date);
    }

    public function filterModifiedDate(string $date, string $format, ?object $post = null): string
    {
        if (!$post || $this->shouldBypassDisplayConversion($format)) {
            return $date;
        }

        $format = $format ?: $this->defaultDateFormat;

        return JalaliFormatter::format($format, $post->post_modified_gmt ?: $post->post_modified);
    }

    public function filterModifiedTime(string $time, string $format, ?object $post = null): string
    {
        if (!$post || $this->shouldBypassDisplayConversion($format)) {
            return $time;
        }

        $format = $format ?: $this->defaultTimeFormat;

        return JalaliFormatter::format($format, $post->post_modified_gmt ?: $post->post_modified);
    }

    public function filterCommentDate(string $date, string $format, ?object $comment = null): string
    {
        if (!$comment || $this->shouldBypassDisplayConversion($format)) {
            return $date;
        }

        $format = $format ?: $this->defaultDateFormat;

        return JalaliFormatter::format($format, $comment->comment_date);
    }

    public function filterCommentTime(string $time, string $format, bool $gmt, bool $translate, ?object $comment = null): string
    {
        if (!$comment || $this->shouldBypassDisplayConversion($format)) {
            return $time;
        }

        $format = $format ?: $this->defaultTimeFormat;
        $source = $gmt ? $comment->comment_date_gmt : $comment->comment_date;

        return JalaliFormatter::format($format, $source);
    }

    public function filterGetPostTime(string $time, string $format, bool $gmt): string
    {
        $post = get_post();
        if (!$post || $this->shouldBypassDisplayConversion($format)) {
            return $time;
        }

        $format = $format ?: $this->defaultTimeFormat;
        $source = $gmt
            ? ($post->post_date_gmt ?: $post->post_date)
            : $post->post_date;

        return JalaliFormatter::format($format, $source);
    }

    // ── Tier 2 callback ───────────────────────────────────────────────

    public function filterWpDate(string $date, string $format, int $timestamp, ?\DateTimeZone $timezone = null): string
    {
        if (self::$inFilter) {
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
        if (self::$inFilter || $this->shouldBypassDisplayConversion($format)) {
            return $date;
        }

        self::$inFilter = true;
        try {
            $timezone = $gmt ? new \DateTimeZone('UTC') : null;
            $result = JalaliFormatter::format($format, $timestamp, $timezone);
        } finally {
            self::$inFilter = false;
        }

        return $result;
    }

    private function shouldBypassDisplayConversion(string $format): bool
    {
        static $machineFormats = null;

        if ($machineFormats === null) {
            $machineFormats = array_values(array_unique(array_filter([
                'U',
                'c',
                'r',
                \DATE_ATOM,
                \DATE_COOKIE,
                \DATE_ISO8601,
                defined('DATE_ISO8601_EXPANDED') ? \DATE_ISO8601_EXPANDED : null,
                \DATE_RFC822,
                \DATE_RFC850,
                \DATE_RFC1036,
                \DATE_RFC1123,
                'D, d M Y H:i:s \\G\\M\\T',
                \DATE_RFC2822,
                \DATE_RFC3339,
                \DATE_RFC3339_EXTENDED,
                \DATE_W3C,
            ], static fn ($value) => is_string($value) && $value !== '')));
        }

        return in_array($format, $machineFormats, true);
    }
}
