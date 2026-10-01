<?php

namespace PersianKit\Modules\WooCommerce;

use PersianKit\Dependencies\Eram\Daynum\CivilDateTime;
use PersianKit\Dependencies\Eram\Abzar\Digits\DigitConverter;

defined('ABSPATH') || exit;

class WooOrderMonthFilter
{
    private const QUERY_VAR = 'persian_kit_wc_month';
    private const SCREEN_LEGACY = 'edit-shop_order';
    private const SCREEN_HPOS = 'woocommerce_page_wc-orders';

    /**
     * The legacy (posts-based) orders screen is covered by
     * {@see \PersianKit\Modules\DateConversion\PostTypeMonthFilter}, which renders
     * the Jalali select for every post type. This class handles the HPOS screen.
     */
    public function register(): void
    {
        add_action('woocommerce_order_list_table_restrict_manage_orders', [$this, 'renderHposFilter'], 20, 2);
        add_filter('woocommerce_order_list_table_prepare_items_query_args', [$this, 'filterOrderQueryArgs'], 20);
        add_action('current_screen', [$this, 'disableCoreMonthsFilter']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function enqueueAssets(): void
    {
        $screen = get_current_screen();
        if ($screen === null || !$this->isOrdersScreen($screen)) {
            return;
        }

        wp_enqueue_style(
            'persian-kit-woo-order-filter',
            PERSIAN_KIT_URL . 'public/css/woo-order-filter.css',
            [],
            PERSIAN_KIT_VERSION
        );
    }

    /**
     * The Jalali select replaces WooCommerce's Gregorian months drop-down, as
     * it does on the posts screens.
     */
    public function disableCoreMonthsFilter(?\WP_Screen $screen = null): void
    {
        if ($screen === null || $screen->id !== self::SCREEN_HPOS || !function_exists('wc_get_order_types')) {
            return;
        }

        foreach (wc_get_order_types('view-orders') as $orderType) {
            add_filter("woocommerce_{$orderType}_list_table_disable_months_filter", '__return_true');
        }
    }

    private function isOrdersScreen(\WP_Screen $screen): bool
    {
        return $screen->id === self::SCREEN_LEGACY || $screen->id === self::SCREEN_HPOS;
    }

    public function renderHposFilter(string $orderType, string $which): void
    {
        if ($which !== 'top' || !$this->supportsOrderType($orderType)) {
            return;
        }

        $this->renderFilterSelect();
    }

    /**
     * Runs only for the HPOS orders list table query, never for other
     * wc_get_orders() calls.
     *
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function filterOrderQueryArgs(array $args): array
    {
        $range = $this->selectedGregorianRange();
        if ($range === null) {
            return $args;
        }

        $args['date_created'] = $range['start'] . '...' . $range['end'];

        return $args;
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
        $raw = DigitConverter::toEnglish($raw);

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

        $orderIds = wc_get_orders([
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'ASC',
            'return' => 'ids',
        ]);

        $oldestId = is_array($orderIds) ? reset($orderIds) : false;
        $oldestOrder = $oldestId ? $this->loadOrder((int) $oldestId) : null;
        if (!$oldestOrder || !method_exists($oldestOrder, 'get_date_created') || !$oldestOrder->get_date_created()) {
            return [];
        }

        $oldestMonth = CivilDateTime::fromDateTime($oldestOrder->get_date_created())->jalali()->startOfMonth()->withTime(0, 0, 0);
        $currentMonth = CivilDateTime::fromDateTime($this->currentDateTime())->jalali()->startOfMonth()->withTime(0, 0, 0);

        $options = [];
        $cursor = $currentMonth;

        while ($cursor->greaterThanOrEqual($oldestMonth)) {
            $jalali = $cursor->jalali();
            $value = sprintf('%04d%02d', $jalali->year(), $jalali->month());
            $label = DigitConverter::toPersian($jalali->withLocale('fa')->format('F Y'));

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

    protected function loadOrder(int $orderId): ?object
    {
        $order = wc_get_order($orderId);

        return is_object($order) ? $order : null;
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
    }
}
