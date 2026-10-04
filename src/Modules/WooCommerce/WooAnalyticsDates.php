<?php

namespace PersianKit\Modules\WooCommerce;

use PersianKit\Modules\DateConversion\JalaliScript;

defined('ABSPATH') || exit;

/**
 * Jalali dates in WooCommerce Analytics and on the WooCommerce home: the
 * wc-admin pages load public/js/woocommerce-analytics-dates.js, which puts
 * Jalali versions of WooCommerce's and WordPress's date helpers in place
 * right after each one loads, before the screens read them.
 */
class WooAnalyticsDates
{
    public const HANDLE = 'persian-kit-woocommerce-analytics-dates';

    /** The scripts replaced, by handle, and the installer run right after each. */
    private const INSTALLERS = [
        'wc-date' => 'installWcDate',
    ];

    public function register(): void
    {
        // After WooCommerce registers its scripts (10) and enqueues the page's (15).
        add_action('admin_enqueue_scripts', [$this, 'enqueue'], 20);
    }

    public function enqueue(string $hookSuffix = ''): void
    {
        if ($hookSuffix !== 'woocommerce_page_wc-admin' || !wp_script_is('wc-date', 'registered')) {
            return;
        }

        JalaliScript::register();

        wp_register_script(
            self::HANDLE,
            PERSIAN_KIT_URL . 'public/js/woocommerce-analytics-dates.js',
            [JalaliScript::HANDLE],
            PERSIAN_KIT_VERSION,
            true
        );

        wp_add_inline_script(
            self::HANDLE,
            'window.persianKitAnalyticsDates = ' . wp_json_encode([
                // As WooCommerce groups weeks on the server.
                'startOfWeek' => (int) get_option('start_of_week', 0),
                'labels'      => [
                    'weekOf' => __('Week of', 'persian-kit'),
                ],
            ]) . ';',
            'before'
        );

        // The script loads before each script it replaces: none of them
        // depends on it, so it would print too late otherwise.
        $scripts = wp_scripts();
        foreach (self::INSTALLERS as $handle => $installer) {
            $script = $scripts->query($handle, 'registered');
            if (!$script instanceof \_WP_Dependency) {
                continue;
            }

            if (!in_array(self::HANDLE, $script->deps, true)) {
                $script->deps[] = self::HANDLE;
            }
            wp_add_inline_script(
                $handle,
                sprintf('window.PersianKitAnalyticsDates && window.PersianKitAnalyticsDates.%s();', $installer),
                'after'
            );
        }
    }
}
