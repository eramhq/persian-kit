<?php

namespace PersianKit\Modules\DateConversion;

use PersianKit\Dependencies\Eram\Daynum\Instant;

defined('ABSPATH') || exit;

class PostTypeMonthFilter
{
    public const QUERY_VAR = 'persian_kit_jalali_month';
    private const SCREEN_POSTS = 'edit.php';
    private const SCREEN_MEDIA = 'upload.php';

    public function register(): void
    {
        add_filter('pre_months_dropdown_query', [$this, 'suppressCoreDropdown'], 10, 2);
        add_action('restrict_manage_posts', [$this, 'renderFilter'], 20, 2);
        add_action('pre_get_posts', [$this, 'filterPostsQuery'], 20);
    }

    public function suppressCoreDropdown(mixed $months, string $postType): mixed
    {
        if (!$this->supportsPostType($postType)) {
            return $months;
        }

        return [];
    }

    public function renderFilter(string $postType, string $which): void
    {
        if (!$this->supportsNavLocation($postType, $which)) {
            return;
        }

        $options = $this->monthOptions($postType);
        if ($options === []) {
            return;
        }

        $selected = $this->selectedJalaliMonth();
        $label = $this->filterLabelForPostType($postType);

        echo '<label for="persian-kit-filter-by-jalali-date" class="screen-reader-text">' . esc_html($label) . '</label>';
        echo '<select name="' . esc_attr(self::QUERY_VAR) . '" id="persian-kit-filter-by-jalali-date">';
        echo '<option value="0">' . esc_html__('All dates') . '</option>';

        foreach ($options as $option) {
            printf(
                '<option %1$s value="%2$s">%3$s</option>',
                selected($selected, $option['value'], false),
                esc_attr($option['value']),
                esc_html($option['label'])
            );
        }

        echo '</select>';
    }

    public function filterPostsQuery(\WP_Query $query): void
    {
        if (!$this->shouldFilterQuery($query)) {
            return;
        }

        $range = $this->selectedGregorianRange();
        if ($range === null) {
            return;
        }

        $dateQuery = $query->get('date_query');
        if (!is_array($dateQuery)) {
            $dateQuery = [];
        }

        $dateQuery[] = [
            'after' => $range['start'],
            'before' => $range['end'],
            'inclusive' => true,
        ];

        $query->set('m', '');
        $query->set('date_query', $dateQuery);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function monthOptions(string $postType): array
    {
        if (!$this->supportsPostType($postType)) {
            return [];
        }

        $options = [];

        foreach ($this->queryDistinctPostDays($postType) as $postDay) {
            try {
                $jalali = Instant::fromDateTime(
                    new \DateTimeImmutable($postDay . ' 00:00:00', wp_timezone())
                )->jalali();
            } catch (\Throwable $exception) {
                continue;
            }

            $value = sprintf('%04d%02d', $jalali->year(), $jalali->month());

            if (isset($options[$value])) {
                continue;
            }

            $options[$value] = [
                'value' => $value,
                'label' => $this->toPersianDigits($jalali->withLocale('fa')->format('F Y')),
            ];
        }

        return array_values($options);
    }

    public function selectedGregorianRange(): ?array
    {
        $jalaliYearMonth = $this->selectedJalaliMonth();
        if ($jalaliYearMonth === null) {
            return null;
        }

        return $this->jalaliMonthToGregorianRange($jalaliYearMonth);
    }

    public function gregorianRangeForJalaliMonth(string $jalaliYearMonth): ?array
    {
        return $this->jalaliMonthToGregorianRange($jalaliYearMonth);
    }

    public function selectedJalaliMonth(): ?string
    {
        $raw = isset($_GET[self::QUERY_VAR]) ? sanitize_text_field(wp_unslash($_GET[self::QUERY_VAR])) : '';
        $raw = $this->normalizeDigits($raw);

        if ($raw === '' || !preg_match('/^\d{6}$/', $raw)) {
            return null;
        }

        return $raw;
    }

    protected function queryDistinctPostDays(string $postType): array
    {
        global $wpdb;

        $extraChecks = "AND post_status != 'auto-draft'";
        $postStatus = $this->requestedStatus($postType);

        if ($postStatus !== 'trash') {
            $extraChecks .= " AND post_status != 'trash'";
        } else {
            $extraChecks = $wpdb->prepare(' AND post_status = %s', $postStatus);
        }

        $results = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT DATE(post_date) AS post_day
                FROM $wpdb->posts
                WHERE post_type = %s
                $extraChecks
                ORDER BY post_date DESC",
                $postType
            )
        );

        return array_values(array_filter(array_map('strval', (array) $results)));
    }

    protected function supportsPostType(string $postType): bool
    {
        return post_type_exists($postType);
    }

    private function shouldFilterQuery(\WP_Query $query): bool
    {
        if (!is_admin() || !$query->is_main_query()) {
            return false;
        }

        global $pagenow;

        if (!in_array($pagenow, [self::SCREEN_POSTS, self::SCREEN_MEDIA], true)) {
            return false;
        }

        $postType = $query->get('post_type');

        if (!is_string($postType) || $postType === '') {
            $postType = 'post';
        }

        return $this->supportsPostType($postType);
    }

    private function supportsNavLocation(string $postType, string $which): bool
    {
        if (!$this->supportsPostType($postType)) {
            return false;
        }

        if ($postType === 'attachment') {
            return $which === 'bar';
        }

        return $which === 'top';
    }

    private function requestedStatus(string $postType): string
    {
        if ($postType === 'attachment') {
            $attachmentFilter = isset($_GET['attachment-filter']) ? sanitize_key(wp_unslash($_GET['attachment-filter'])) : '';
            $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';

            if ($attachmentFilter === 'trash' || $status === 'trash') {
                return 'trash';
            }

            return '';
        }

        return isset($_GET['post_status']) ? sanitize_key(wp_unslash($_GET['post_status'])) : '';
    }

    private function filterLabelForPostType(string $postType): string
    {
        $object = get_post_type_object($postType);

        return $object->labels->filter_by_date ?? __('Filter by date');
    }

    private function jalaliMonthToGregorianRange(string $jalaliYearMonth): ?array
    {
        if (!preg_match('/^\d{6}$/', $jalaliYearMonth)) {
            return null;
        }

        $year = (int) substr($jalaliYearMonth, 0, 4);
        $month = (int) substr($jalaliYearMonth, 4, 2);

        if ($month < 1 || $month > 12) {
            return null;
        }

        try {
            $start = Instant::fromJalali($year, $month, 1);
            $end = $start->jalali()->endOfMonth();
        } catch (\Throwable $exception) {
            return null;
        }

        return [
            'start' => $start->toDateTimeImmutable()->format('Y-m-d'),
            'end' => $end->toDateTimeImmutable()->format('Y-m-d'),
        ];
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

    private function toPersianDigits(string $value): string
    {
        return strtr($value, [
            '0' => '۰',
            '1' => '۱',
            '2' => '۲',
            '3' => '۳',
            '4' => '۴',
            '5' => '۵',
            '6' => '۶',
            '7' => '۷',
            '8' => '۸',
            '9' => '۹',
        ]);
    }
}
