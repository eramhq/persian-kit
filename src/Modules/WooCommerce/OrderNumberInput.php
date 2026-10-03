<?php

namespace PersianKit\Modules\WooCommerce;

defined('ABSPATH') || exit;

/**
 * Order numbers typed or pasted with Persian digits, as emails may show
 * them: the order tracking form and the admin's order search find the order.
 */
class OrderNumberInput
{
    public function register(): void
    {
        // First, so plugins that make their own order numbers get English digits.
        add_filter('woocommerce_shortcode_order_tracking_order_id', [$this, 'normalizeOrderId'], 1);

        if (is_admin()) {
            // WooCommerce > Orders with order tables (HPOS), and with posts storage.
            add_filter('woocommerce_order_list_table_prepare_items_query_args', [$this, 'normalizeListTableArgs']);
            add_filter('woocommerce_shop_order_search_results', [$this, 'addEnglishDigitResults'], 10, 2);
            // wc_order_search() with order tables.
            add_filter('woocommerce_cot_shop_order_search_results', [$this, 'addEnglishDigitResults'], 10, 2);
        }
    }

    public function normalizeOrderId(mixed $orderId): mixed
    {
        return is_string($orderId) ? persian_kit_to_english_digits($orderId) : $orderId;
    }

    public function normalizeListTableArgs(mixed $args): mixed
    {
        if (is_array($args) && isset($args['s']) && is_string($args['s'])) {
            $args['s'] = persian_kit_to_english_digits($args['s']);
        }

        return $args;
    }

    /**
     * wc_order_search() searches the term as typed, so the term with English
     * digits is searched too. That search runs this filter again, with
     * nothing left to convert.
     */
    public function addEnglishDigitResults(mixed $orderIds, mixed $term): mixed
    {
        if (!is_array($orderIds) || !is_string($term)) {
            return $orderIds;
        }

        $english = persian_kit_to_english_digits($term);
        if ($english === $term) {
            return $orderIds;
        }

        // WooCommerce 9.9 passes the IDs it found as strings.
        return array_values(array_unique(array_map('intval', array_merge($orderIds, wc_order_search($english)))));
    }
}
