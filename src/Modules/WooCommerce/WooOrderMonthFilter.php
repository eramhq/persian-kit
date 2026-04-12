<?php

namespace PersianKit\Modules\WooCommerce;

use PersianKit\Dependencies\Eram\Daynum\Instant;

defined('ABSPATH') || exit;

class WooOrderMonthFilter
{
    private const QUERY_VAR = 'persian_kit_wc_month';

    public function register(): void
    {
        add_action('woocommerce_order_list_table_restrict_manage_orders', [$this, 'renderHposFilter'], 20, 2);
        add_filter('woocommerce_order_query_args', [$this, 'filterOrderQueryArgs'], 20);
        add_action('restrict_manage_posts', [$this, 'renderLegacyFilter'], 20, 2);
        add_action('pre_get_posts', [$this, 'filterLegacyOrderQuery'], 20);
    }

    public function renderHposFilter(string $orderType, string $which): void
    {
        if ($which !== 'top' || !$this->supportsOrderType($orderType)) {
            return;
        }

        $this->renderFilterSelect();
    }

    public function renderLegacyFilter(string $postType, string $which): void
    {
        if ($which !== 'top' || $postType !== 'shop_order') {
            return;
        }

        $this->renderFilterSelect();
    }

    public function filterOrderQueryArgs(array $args): array
    {
        $range = $this->selectedGregorianRange();
        if ($range === null) {
            return $args;
        }

        $args['date_created'] = $range['start'] . '...' . $range['end'];

        return $args;
    }

    public function filterLegacyOrderQuery(\WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $postType = $query->get('post_type');
        if ($postType !== 'shop_order') {
            return;
        }

        $range = $this->selectedGregorianRange();
        if ($range === null) {
            return;
        }

        $query->set('date_query', [
            [
                'after' => $range['start'],
                'before' => $range['end'],
                'inclusive' => true,
            ],
        ]);
    }

    public function selectedGregorianRange(): ?array
    {
        $jalaliYearMonth = $this->selectedJalaliMonth();
        if ($jalaliYearMonth === null) {
            return null;
        }

        return WooDateHelper::jalaliMonthToGregorianRange($jalaliYearMonth);
    }

    public function selectedJalaliMonth(): ?string
    {
        $raw = isset($_GET[self::QUERY_VAR]) ? sanitize_text_field(wp_unslash($_GET[self::QUERY_VAR])) : '';

        if ($raw === '' || !preg_match('/^\d{6}$/', $raw)) {
            return null;
        }

        return $raw;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function monthOptions(): array
    {
        if (!$this->canQueryOrders()) {
            return [];
        }

        $orders = wc_get_orders([
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'ASC',
            'return' => 'objects',
        ]);

        $oldestOrder = is_array($orders) ? reset($orders) : null;
        if (!$oldestOrder || !method_exists($oldestOrder, 'get_date_created') || !$oldestOrder->get_date_created()) {
            return [];
        }

        $oldestMonth = Instant::fromDateTime($oldestOrder->get_date_created())->jalali()->startOfMonth();
        $currentMonth = Instant::fromDateTime($this->currentDateTime())->jalali()->startOfMonth();

        $options = [];
        $cursor = $currentMonth;

        while ($cursor->greaterThanOrEqual($oldestMonth)) {
            $jalali = $cursor->jalali();
            $value = sprintf('%04d%02d', $jalali->year(), $jalali->month());
            $label = WooDateHelper::toPersianDigits($jalali->withLocale('fa')->format('F Y'));

            $options[] = [
                'value' => $value,
                'label' => $label,
            ];

            $cursor = $cursor->jalali()->subMonths(1)->jalali()->startOfMonth();
        }

        return $options;
    }

    protected function currentDateTime(): \DateTimeInterface
    {
        return new \DateTime('now', wp_timezone());
    }

    protected function canQueryOrders(): bool
    {
        return function_exists('wc_get_orders');
    }

    private function supportsOrderType(string $orderType): bool
    {
        if (!function_exists('wc_get_order_types')) {
            return false;
        }

        return in_array($orderType, wc_get_order_types('view-orders'), true);
    }

    private function renderFilterSelect(): void
    {
        $options = $this->monthOptions();
        if ($options === []) {
            return;
        }

        $selected = $this->selectedJalaliMonth();

        echo '<select name="' . esc_attr(self::QUERY_VAR) . '" id="persian-kit-filter-by-jalali-date">';
        echo '<option value="0">' . esc_html__('All Jalali dates', 'persian-kit') . '</option>';

        foreach ($options as $option) {
            printf(
                '<option %1$s value="%2$s">%3$s</option>',
                selected($selected, $option['value'], false),
                esc_attr($option['value']),
                esc_html($option['label'])
            );
        }

        echo '</select>';
        echo '<style>#filter-by-date{display:none;}</style>';
    }
}
