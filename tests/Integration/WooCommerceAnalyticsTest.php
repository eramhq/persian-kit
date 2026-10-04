<?php

namespace PersianKit\Tests\Integration;

use Automattic\WooCommerce\Admin\API\Reports\Orders\Stats\DataStore as OrderStatsDataStore;
use Automattic\WooCommerce\Admin\ReportCSVExporter;
use PersianKit\Modules\WooCommerce\WooAnalyticsDates;
use PersianKit\Modules\WooCommerce\WooAnalyticsExport;
use PersianKit\Modules\WooCommerce\WooAnalyticsIntervals;
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
        // A server another test built may lack WooCommerce's Analytics routes.
        $GLOBALS['wp_rest_server'] = null;
        update_option('timezone_string', 'Asia/Tehran');
        // Analytics counts orders by the date paid unless told otherwise.
        update_option('woocommerce_date_type', 'date_created');
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
    }

    public function tear_down(): void
    {
        $GLOBALS['wp_scripts'] = null;
        remove_all_filters('persian_kit_analytics_jalali_intervals');
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

    public function test_months_are_jalali_months_whose_sums_match_the_days(): void
    {
        (new WooAnalyticsIntervals())->register();
        $customer = self::factory()->user->create(['role' => 'customer']);
        // The last minutes of Farvardin are in Gregorian April, as are the first of Ordibehesht.
        $this->order('2025-04-20 23:50:00', 100, $customer);
        $this->order('2025-04-10 10:00:00', 50, $customer);
        $this->order('2025-04-21 00:10:00', 20);
        $this->order('2025-06-01 12:00:00', 7);

        $response = $this->stats(['interval' => 'month', 'order' => 'asc', 'per_page' => 100]);
        $intervals = $response->get_data()['intervals'];

        $this->assertSame(200, $response->get_status());
        $this->assertSame(['1404-01', '1404-02', '1404-03'], array_column($intervals, 'interval'));
        $this->assertSame(['2025-03-21 00:00:00', '2025-04-21 00:00:00', '2025-05-22 00:00:00'], array_column($intervals, 'date_start'));
        $this->assertSame(['2025-04-20 23:59:59', '2025-05-21 23:59:59', '2025-06-21 23:59:59'], array_column($intervals, 'date_end'));
        $this->assertSame('2025-03-20 20:30:00', $intervals[0]['date_start_gmt']);
        $this->assertSame([150.0, 20.0, 7.0], array_map(static fn (array $interval): float => (float) $interval['subtotals']->net_revenue, $intervals));
        $this->assertSame([2, 1, 1], array_map(static fn (array $interval): int => (int) $interval['subtotals']->orders_count, $intervals));
        // Each month is asked of WooCommerce, so averages are its own.
        $this->assertEqualsWithDelta(75.0, (float) $intervals[0]['subtotals']->avg_order_value, 0.001);
        $this->assertSame(1, (int) $intervals[0]['subtotals']->total_customers, 'one customer, two orders');
        $this->assertSame('3', (string) $response->get_headers()['X-WP-Total']);

        // The months add up to the days, and to the totals.
        $days = $this->stats(['interval' => 'day', 'per_page' => 100], false)->get_data();
        $this->assertSame(177.0, array_sum(array_map(static fn (array $day): float => (float) $day['subtotals']->net_revenue, $days['intervals'])));
        $this->assertSame(177.0, (float) $response->get_data()['totals']['net_revenue']);
        $this->assertSame($days['totals']['orders_count'], $response->get_data()['totals']['orders_count']);
    }

    public function test_without_the_flag_or_when_opted_out_months_stay_woocommerces(): void
    {
        (new WooAnalyticsIntervals())->register();

        $this->assertSame('2025-03', $this->stats(['interval' => 'month', 'order' => 'asc'], false)->get_data()['intervals'][0]['interval']);

        add_filter('persian_kit_analytics_jalali_intervals', static fn (bool $enabled, string $route): bool => $route !== '/wc-analytics/reports/revenue/stats', 10, 2);
        $this->assertSame('2025-03', $this->stats(['interval' => 'month', 'order' => 'asc'])->get_data()['intervals'][0]['interval']);
    }

    public function test_seasons_and_pages(): void
    {
        (new WooAnalyticsIntervals())->register();

        // 1 Farvardin 1403 to 30 Esfand 1403, a leap year.
        $seasons = $this->stats(['interval' => 'quarter', 'after' => '2024-03-20T00:00:00', 'before' => '2025-03-20T23:59:59', 'order' => 'asc'])->get_data()['intervals'];
        $this->assertSame(['1403-1', '1403-2', '1403-3', '1403-4'], array_column($seasons, 'interval'));
        $this->assertSame(['2024-03-20 00:00:00', '2024-06-21 00:00:00', '2024-09-22 00:00:00', '2024-12-21 00:00:00'], array_column($seasons, 'date_start'));
        $this->assertSame('2025-03-20 23:59:59', $seasons[3]['date_end']);

        // Newest first by default; only the page's months are asked for.
        $page = $this->stats(['interval' => 'month', 'per_page' => 2, 'page' => 2]);
        $this->assertSame(['1404-01'], array_column($page->get_data()['intervals'], 'interval'));
        $this->assertSame('3', (string) $page->get_headers()['X-WP-Total']);
        $this->assertSame('2', (string) $page->get_headers()['X-WP-TotalPages']);

        $years = $this->stats(['interval' => 'year', 'after' => '2025-03-01T00:00:00', 'before' => '2025-04-01T23:59:59', 'order' => 'asc'])->get_data()['intervals'];
        $this->assertSame([['1403', '2025-03-01 00:00:00', '2025-03-20 23:59:59'], ['1404', '2025-03-21 00:00:00', '2025-04-01 23:59:59']], array_map(
            static fn (array $interval): array => [$interval['interval'], $interval['date_start'], $interval['date_end']],
            $years
        ));
    }

    public function test_an_extensions_stats_endpoint_gets_jalali_months_too(): void
    {
        (new WooAnalyticsIntervals())->register();
        add_action('rest_api_init', static function (): void {
            // Counts the days asked for, in WooCommerce's stats shape.
            register_rest_route('wc-analytics', '/reports/pk-test/stats', [
                'methods'             => 'GET',
                'permission_callback' => '__return_true',
                'callback'            => static function (\WP_REST_Request $request): array {
                    $days = (new \DateTime($request['before']))->diff(new \DateTime($request['after']))->days + 1;

                    return ['totals' => ['days' => $days], 'intervals' => []];
                },
            ]);
            register_rest_route('wc-analytics', '/reports/pk-other/stats', [
                'methods'             => 'GET',
                'permission_callback' => '__return_true',
                'callback'            => static fn (): array => ['rows' => []],
            ]);
        });
        $GLOBALS['wp_rest_server'] = null;

        $months = $this->stats(['interval' => 'month', 'order' => 'asc'], true, 'pk-test')->get_data()['intervals'];
        $this->assertSame([31, 31, 31], array_map(static fn (array $interval): int => $interval['subtotals']->days, $months));

        // Another shape is left to the endpoint.
        $this->assertSame(['rows' => []], $this->stats(['interval' => 'month'], true, 'pk-other')->get_data());
    }

    public function test_the_emailed_export_has_a_jalali_date_next_to_the_gregorian_one(): void
    {
        (new WooAnalyticsExport())->register();
        $this->order('2025-10-01 12:00:00', 40);

        // As Action Scheduler runs it, with no browser.
        $exporter = new ReportCSVExporter('orders', [
            'after'  => '2025-09-23T00:00:00',
            'before' => '2025-10-22T23:59:59',
        ]);
        $exporter->set_filename('pk-test-orders-' . wp_generate_password(6, false));
        $exporter->set_page(1);
        $exporter->generate_file();

        $lines = array_values(array_filter(explode("\n", str_replace("\xEF\xBB\xBF", '', $exporter->get_headers_row_file() . $exporter->get_file()))));
        // The file and its headers file.
        foreach (glob(ReportCSVExporter::get_reports_directory() . pathinfo($exporter->get_filename(), PATHINFO_FILENAME) . '*') ?: [] as $file) {
            unlink($file); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink
        }

        $header = str_getcsv($lines[0], ',', '"', '');
        $row = array_combine($header, str_getcsv($lines[1], ',', '"', ''));
        $this->assertSame(['Date', 'Date (Jalali)'], array_slice($header, 0, 2));
        $this->assertSame('1404/07/09', $row['Date (Jalali)']);
        $this->assertStringStartsWith('2025-10-01', $row['Date']);
    }

    /**
     * Revenue stats from 1 Farvardin to 31 Khordad 1404, asked as Analytics does.
     *
     * @param array<string, mixed> $params
     */
    private function stats(array $params, bool $jalali = true, string $report = 'revenue'): \WP_REST_Response
    {
        $request = new \WP_REST_Request('GET', "/wc-analytics/reports/{$report}/stats");
        $request->set_query_params(array_merge([
            'after'               => '2025-03-21T00:00:00',
            'before'              => '2025-06-21T23:59:59',
            'force_cache_refresh' => true,
        ], $jalali ? [WooAnalyticsIntervals::FLAG => 'jalali'] : [], $params));

        return rest_do_request($request);
    }

    private function order(string $created, float $total, int $customer = 0): void
    {
        $product = new \WC_Product_Simple();
        $product->set_name('PK test analytics');
        $product->set_regular_price((string) $total);
        $product->save();

        $order = wc_create_order(['customer_id' => $customer]);
        $order->add_product($product, 1);
        $order->calculate_totals(false);
        $order->set_date_created($created);
        $order->set_status('processing');
        $order->save();

        OrderStatsDataStore::sync_order($order->get_id());
    }

    private function printedScripts(): string
    {
        ob_start();
        wp_scripts()->do_items(false, 0);
        wp_scripts()->do_items(false, 1);

        return (string) ob_get_clean();
    }
}
