<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Modules\WooCommerce\WooAnalyticsDates;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * Jalali dates in WooCommerce Analytics. Runs when WooCommerce is loaded
 * (see tests/bootstrap.php).
 */
class WooCommerceAnalyticsTest extends WordPressIntegrationTestCase
{
    public function set_up(): void
    {
        parent::set_up();

        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is not installed next to the plugin.');
        }

        $GLOBALS['wp_scripts'] = null;
    }

    public function tear_down(): void
    {
        $GLOBALS['wp_scripts'] = null;
        parent::tear_down();
    }

    public function test_the_script_prints_before_wc_date_and_replaces_it_right_after(): void
    {
        wp_register_script('wc-date', 'https://example.com/wc/date/index.js', ['moment'], '1', true);
        wp_register_script('wc-admin-app', 'https://example.com/wc/app/index.js', ['wc-date'], '1', true);

        (new WooAnalyticsDates())->enqueue('woocommerce_page_wc-admin');
        wp_enqueue_script('wc-admin-app');

        $html = $this->printedScripts();
        $script = strpos($html, 'public/js/woocommerce-analytics-dates.js');
        $wooDate = strpos($html, 'wc/date/index.js');
        $installer = strpos($html, 'window.PersianKitAnalyticsDates.installWcDate();');
        $app = strpos($html, 'wc/app/index.js');

        $this->assertNotFalse($script);
        $this->assertTrue($script < $wooDate && $wooDate < $installer && $installer < $app, $html);
        $this->assertStringContainsString('"startOfWeek":' . (int) get_option('start_of_week'), $html);
        $this->assertLessThan($script, strpos($html, 'public/js/jalali.js'));
    }

    public function test_wp_date_is_wrapped_right_after_it_loads(): void
    {
        wp_register_script('wc-date', 'https://example.com/wc/date/index.js', [], '1', true);

        (new WooAnalyticsDates())->enqueue('woocommerce_page_wc-admin');
        wp_enqueue_script('wp-date');

        $html = $this->printedScripts();
        $script = strpos($html, 'public/js/woocommerce-analytics-dates.js');
        $wpDate = strpos($html, 'wp-includes/js/dist/date');
        $installer = strpos($html, 'window.PersianKitAnalyticsDates.installWpDate();');

        $this->assertNotFalse($script);
        $this->assertTrue($script < $wpDate && $wpDate < $installer, $html);
    }

    public function test_other_admin_pages_are_left_alone(): void
    {
        wp_register_script('wc-date', 'https://example.com/wc/date/index.js', [], '1', true);

        (new WooAnalyticsDates())->enqueue('edit.php');

        $this->assertFalse(wp_script_is(WooAnalyticsDates::HANDLE, 'registered'));
        $this->assertSame([], wp_scripts()->query('wc-date')->deps);
    }

    private function printedScripts(): string
    {
        ob_start();
        wp_scripts()->do_items(false, 0);
        wp_scripts()->do_items(false, 1);

        return (string) ob_get_clean();
    }
}
