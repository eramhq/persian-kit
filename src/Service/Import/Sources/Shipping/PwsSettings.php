<?php

namespace PersianKit\Service\Import\Sources\Shipping;

use PersianKit\Service\Import\AbstractSource;
use PersianKit\Service\Import\Settings\SettingRow;
use PersianKit\Service\Import\Settings\SettingStatus;

defined('ABSPATH') || exit;

/**
 * Persian WooCommerce Shipping's settings and shipping methods next to
 * Persian Kit, which has no shipping methods.
 */
class PwsSettings
{
    /** Its shipping methods, by WooCommerce method id. */
    public const METHODS = [
        'WC_Courier_Method'    => 'Courier',
        'WC_Forehand_Method'   => 'Pishtaz post',
        'WC_Tipax_Method'      => 'Tipax',
        'Tapin_Pishtaz_Method' => 'Tapin Pishtaz',
        'Tapin_Special_Method' => 'Tapin special',
    ];

    public static function tapinEnabled(): bool
    {
        $tapin = get_option('pws_tapin', []);

        return is_array($tapin) && AbstractSource::truthy($tapin['enable'] ?? false);
    }

    /**
     * @return list<SettingRow>
     */
    public function rows(): array
    {
        $rows = [
            new SettingRow(
                'state_city',
                __('Province and city lists at checkout', 'persian-kit'),
                SettingStatus::Close,
                __('City suggestions', 'persian-kit'),
                ['woocommerce.city_select' => true],
                __('Customers pick the province from WooCommerce\'s list and get suggestions for the city as they type, from the official list. Districts are not listed.', 'persian-kit')
            ),
        ];

        if (self::tapinEnabled()) {
            $rows[] = new SettingRow(
                'tapin',
                __('Tapin', 'persian-kit'),
                SettingStatus::Automatic,
                '',
                [],
                __('Addresses saved with Tapin\'s numbers are read with its list.', 'persian-kit')
            );
        }

        $zones = self::methodsByZone();
        if ($zones !== []) {
            $rows[] = new SettingRow('methods', __('Shipping methods: courier, Tipax, post and Tapin', 'persian-kit'), SettingStatus::NotYet, '', [], __('Persian Kit has no shipping methods. See "Before you deactivate".', 'persian-kit'));
        }

        if (self::cityPriceCount() > 0) {
            $rows[] = new SettingRow('city_prices', __('Shipping prices per city', 'persian-kit'), SettingStatus::NotYet, '', [], __('WooCommerce zones can not target cities; use postcodes in a zone instead.', 'persian-kit'));
        }

        foreach (['pws_map' => __('Map at checkout', 'persian-kit'), 'pws_sms' => __('Text messages', 'persian-kit')] as $option => $label) {
            $value = get_option($option, []);
            if (is_array($value) && array_filter($value, [AbstractSource::class, 'truthy']) !== []) {
                $rows[] = new SettingRow(str_replace('pws_', '', $option), $label, SettingStatus::NotYet, '', [], __('Persian Kit has no match yet.', 'persian-kit'));
            }
        }

        return $rows;
    }

    /**
     * Zones with its methods turned on: name, link, its methods, and whether
     * the zone has any other method.
     *
     * @return list<array{id: int, name: string, methods: list<string>, others: int}>
     */
    public static function methodsByZone(): array
    {
        global $wpdb;

        $ids = array_keys(self::METHODS);
        $in = implode(',', array_fill(0, count($ids), '%s'));

        // WooCommerce's tables may be gone with it.
        $suppress = $wpdb->suppress_errors(true);
        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT m.zone_id, m.method_id, m.is_enabled, z.zone_name
            FROM {$wpdb->prefix}woocommerce_shipping_zone_methods m
            LEFT JOIN {$wpdb->prefix}woocommerce_shipping_zones z ON z.zone_id = m.zone_id
            WHERE m.zone_id IN (SELECT zone_id FROM {$wpdb->prefix}woocommerce_shipping_zone_methods WHERE method_id IN ({$in}) AND is_enabled = 1)",
            $ids
        ), ARRAY_A);
        // phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
        $wpdb->suppress_errors($suppress);

        $zones = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $id = (int) $row['zone_id'];
            $zones[$id] ??= [
                'id'      => $id,
                // Zone 0 is "Locations not covered by your other zones".
                'name'    => $id === 0 ? __('Locations not covered by your other zones', 'persian-kit') : (string) $row['zone_name'],
                'methods' => [],
                'others'  => 0,
            ];

            if (!(int) $row['is_enabled']) {
                continue;
            }
            if (isset(self::METHODS[$row['method_id']])) {
                $zones[$id]['methods'][] = self::METHODS[$row['method_id']];
            } else {
                $zones[$id]['others']++;
            }
        }

        return array_values($zones);
    }

    /**
     * How many provinces, cities or districts have their own shipping prices.
     */
    public static function cityPriceCount(): int
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like('nabik_taxonomy_') . '%'));
    }
}
