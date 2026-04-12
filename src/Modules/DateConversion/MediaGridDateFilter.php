<?php

namespace PersianKit\Modules\DateConversion;

defined('ABSPATH') || exit;

class MediaGridDateFilter
{
    private const SCREEN_MEDIA = 'upload.php';
    private const SCRIPT_HANDLE = 'persian-kit-media-grid-date-filter';

    public function __construct(private readonly PostTypeMonthFilter $monthFilter)
    {
    }

    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_filter('media_library_months_with_files', [$this, 'suppressCoreMonths']);
        add_filter('media_view_settings', [$this, 'filterMediaViewSettings'], 20, 2);
        add_filter('ajax_query_attachments_args', [$this, 'filterAttachmentQueryArgs']);
    }

    public function enqueue(string $hookSuffix = ''): void
    {
        if ($hookSuffix !== self::SCREEN_MEDIA) {
            return;
        }

        wp_enqueue_script(
            self::SCRIPT_HANDLE,
            PERSIAN_KIT_URL . 'public/js/media-grid-date-filter.js',
            [],
            PERSIAN_KIT_VERSION,
            true
        );
    }

    public function suppressCoreMonths(mixed $months): mixed
    {
        if (!$this->isUploadScreen()) {
            return $months;
        }

        return [];
    }

    /**
     * @param array<string, mixed> $settings
     * @param mixed $post
     * @return array<string, mixed>
     */
    public function filterMediaViewSettings(array $settings, mixed $post = null): array
    {
        if (!$this->isUploadScreen()) {
            return $settings;
        }

        $settings['months'] = array_map(
            static fn (array $option): array => [
                'text' => $option['label'],
                'jalaliYearMonth' => $option['value'],
            ],
            $this->monthFilter->monthOptions('attachment')
        );

        return $settings;
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function filterAttachmentQueryArgs(array $query): array
    {
        $jalaliYearMonth = $this->requestedJalaliMonth();
        if ($jalaliYearMonth === null) {
            return $query;
        }

        $range = $this->monthFilter->gregorianRangeForJalaliMonth($jalaliYearMonth);
        if ($range === null) {
            return $query;
        }

        $dateQuery = $query['date_query'] ?? [];
        if (!is_array($dateQuery)) {
            $dateQuery = [];
        }

        $dateQuery[] = [
            'after' => $range['start'],
            'before' => $range['end'],
            'inclusive' => true,
        ];

        unset($query['year'], $query['monthnum'], $query['m']);

        $query['date_query'] = $dateQuery;

        return $query;
    }

    private function isUploadScreen(): bool
    {
        global $pagenow;

        return $pagenow === self::SCREEN_MEDIA;
    }

    private function requestedJalaliMonth(): ?string
    {
        $raw = '';

        if (isset($_REQUEST['query']) && is_array($_REQUEST['query'])) {
            $requestQuery = wp_unslash($_REQUEST['query']);

            if (isset($requestQuery[PostTypeMonthFilter::QUERY_VAR]) && (is_string($requestQuery[PostTypeMonthFilter::QUERY_VAR]) || is_numeric($requestQuery[PostTypeMonthFilter::QUERY_VAR]))) {
                $raw = sanitize_text_field((string) $requestQuery[PostTypeMonthFilter::QUERY_VAR]);
            }
        }

        if ($raw === '' && isset($_REQUEST[PostTypeMonthFilter::QUERY_VAR]) && (is_string($_REQUEST[PostTypeMonthFilter::QUERY_VAR]) || is_numeric($_REQUEST[PostTypeMonthFilter::QUERY_VAR]))) {
            $raw = sanitize_text_field(wp_unslash((string) $_REQUEST[PostTypeMonthFilter::QUERY_VAR]));
        }

        $raw = $this->normalizeDigits($raw);

        if ($raw === '' || !preg_match('/^\d{6}$/', $raw)) {
            return null;
        }

        return $raw;
    }

    private function normalizeDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0',
            '۱' => '1',
            '۲' => '2',
            '۳' => '3',
            '۴' => '4',
            '۵' => '5',
            '۶' => '6',
            '۷' => '7',
            '۸' => '8',
            '۹' => '9',
            '٠' => '0',
            '١' => '1',
            '٢' => '2',
            '٣' => '3',
            '٤' => '4',
            '٥' => '5',
            '٦' => '6',
            '٧' => '7',
            '٨' => '8',
            '٩' => '9',
        ]);
    }
}
