<?php

namespace PersianKit\Service\Import\Sources\Shipping;

use PersianKit\Service\Import\AbstractSource;

defined('ABSPATH') || exit;

/**
 * Persian WooCommerce Shipping (حمل و نقل ووکامرس): provinces, cities and
 * districts are terms of its state_city taxonomy, and addresses hold their
 * term IDs, or Tapin's own IDs while its Tapin mode is on (pws_tapin).
 */
class PwsSource extends AbstractSource
{
    public const KEY = 'persian-woocommerce-shipping';

    public const TAXONOMY = 'state_city';

    public function key(): string
    {
        return self::KEY;
    }

    public function name(): string
    {
        return __('Persian WooCommerce Shipping', 'persian-kit');
    }

    public function pluginFile(): string
    {
        return 'persian-woocommerce-shipping/woocommerce-shipping.php';
    }

    public function hasData(): bool
    {
        if (get_option('pws_install_cities') || self::arrayOption('pws_tapin') !== []) {
            return true;
        }

        global $wpdb;

        // Read directly: the taxonomy is only registered while it is active.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        return (bool) $wpdb->get_var($wpdb->prepare("SELECT 1 FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s LIMIT 1", self::TAXONOMY));
    }

    protected function isLoaded(): bool
    {
        return defined('PWS_VERSION');
    }
}
