<?php

namespace PersianKit\Service\Import\Woo;

use Automattic\WooCommerce\Utilities\OrderUtil;
use PersianKit\Service\Import\Iran\IranProvinces;

defined('ABSPATH') || exit;

/**
 * Orders in Iran whose address still needs converting: a province that is
 * not a WooCommerce code, or a city saved as a number. Read from the order
 * tables or from post meta, whichever WooCommerce uses. Refunds have no
 * address and are left out; subscriptions are included while WooCommerce
 * Subscriptions is active, since renewals copy their address.
 */
final class OrderCandidates
{
    /**
     * @param bool $legacyOnly Only the old two-letter province codes.
     * @return list<int>
     */
    public static function ids(int $after, int $limit, bool $legacyOnly = false): array
    {
        global $wpdb;

        $sql = self::select($legacyOnly, $after) . ' ORDER BY id ASC LIMIT %d';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        return array_map('intval', (array) $wpdb->get_col($wpdb->prepare($sql, $limit)));
    }

    public static function count(bool $legacyOnly = false): int
    {
        global $wpdb;

        // Built from constants and an integer only.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
        return (int) $wpdb->get_var('SELECT COUNT(*) FROM (' . self::select($legacyOnly, 0) . ') counted');
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        $types = ['shop_order'];
        if (class_exists('WC_Subscriptions') || function_exists('wcs_get_subscription')) {
            $types[] = 'shop_subscription';
        }

        return $types;
    }

    public static function usesOrderTables(): bool
    {
        return class_exists(OrderUtil::class) && OrderUtil::custom_orders_table_usage_is_enabled();
    }

    /**
     * The ids after $after, from both address types. Made of constants and
     * an integer, so it needs no placeholders.
     */
    private static function select(bool $legacyOnly, int $after): string
    {
        $alias = 'id';
        global $wpdb;

        $types = "'" . implode("','", array_map('esc_sql', self::types())) . "'";
        $codes = "'" . implode("','", array_keys(IranProvinces::NAMES)) . "'";
        $legacy = "'" . implode("','", array_keys(IranProvinces::LEGACY_TWO_LETTER)) . "'";

        if (self::usesOrderTables()) {
            $orders = $wpdb->prefix . 'wc_orders';
            $addresses = $wpdb->prefix . 'wc_order_addresses';
            $needs = $legacyOnly
                ? "a.state IN ({$legacy})"
                : "((a.state <> '' AND a.state NOT IN ({$codes})) OR a.city REGEXP '^[0-9]+$')";

            return "SELECT DISTINCT a.order_id AS {$alias} FROM {$addresses} a
                INNER JOIN {$orders} o ON o.id = a.order_id
                WHERE o.type IN ({$types}) AND a.country = 'IR' AND a.order_id > {$after} AND {$needs}";
        }

        $parts = [];
        foreach (['billing', 'shipping'] as $type) {
            $needs = $legacyOnly
                ? "st.meta_value IN ({$legacy})"
                : "((st.meta_value <> '' AND st.meta_value NOT IN ({$codes})) OR ci.meta_value REGEXP '^[0-9]+$')";
            $parts[] = "SELECT p.ID AS {$alias} FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} co ON co.post_id = p.ID AND co.meta_key = '_{$type}_country' AND co.meta_value = 'IR'
                LEFT JOIN {$wpdb->postmeta} st ON st.post_id = p.ID AND st.meta_key = '_{$type}_state'
                LEFT JOIN {$wpdb->postmeta} ci ON ci.post_id = p.ID AND ci.meta_key = '_{$type}_city'
                WHERE p.post_type IN ({$types}) AND p.ID > {$after} AND {$needs}";
        }

        return 'SELECT DISTINCT ' . $alias . ' FROM (' . implode(' UNION ', $parts) . ') candidates';
    }
}
