<?php

namespace PersianKit\Tests\Unit\WooCommerce;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\WooCommerce\WooAnalyticsExport;
use PersianKit\Service\Language\ContentLanguage;
use PersianKit\Tests\Unit\Support\UsesLanguages;
use PHPUnit\Framework\TestCase;

class WooAnalyticsExportTest extends TestCase
{
    use UsesLanguages;

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        ContentLanguage::reset();
        Functions\when('__')->returnArg();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_a_jalali_column_follows_each_date_column(): void
    {
        $columns = (new WooAnalyticsExport())->addColumns([
            'date_created'  => 'Date',
            'order_number'  => 'Order #',
            'registered'    => 'Sign Up',
            'date_paid_gmt' => 'Paid (GMT)',
        ]);

        $this->assertSame([
            'date_created'        => 'Date',
            'date_created_jalali' => 'Date (Jalali)',
            'order_number'        => 'Order #',
            'registered'          => 'Sign Up',
            'registered_jalali'   => 'Sign Up (Jalali)',
            'date_paid_gmt'       => 'Paid (GMT)',
        ], $columns);
    }

    public function test_rows_get_the_jalali_day_of_their_dates(): void
    {
        $row = (new WooAnalyticsExport())->addValues([
            'date'    => '2025-10-01 00:00:00',
            'expires' => 'N/A',
            'orders'  => 3,
        ]);

        $this->assertSame(['date' => '2025-10-01 00:00:00', 'expires' => 'N/A', 'orders' => 3, 'date_jalali' => '1404/07/09', 'expires_jalali' => ''], $row);
    }

    public function test_jalali_days_across_nowruz_and_leap_years(): void
    {
        $this->assertSame('1403/12/30', WooAnalyticsExport::jalaliDate('2025-03-20'));
        $this->assertSame('1404/01/01', WooAnalyticsExport::jalaliDate('2025-03-21T00:10:00'));
        $this->assertSame('', WooAnalyticsExport::jalaliDate('2025-02-30'));
        $this->assertSame('', WooAnalyticsExport::jalaliDate(null));
        $this->assertTrue(WooAnalyticsExport::isDateColumn('next_payment_date'));
        $this->assertFalse(WooAnalyticsExport::isDateColumn('updated'));
    }

    public function test_every_report_type_gets_the_filters_on_persian_sites_only(): void
    {
        $export = new WooAnalyticsExport();
        $map = ['revenue' => 'Revenue', 'pk-test' => 'PkTest'];

        $this->assertSame($map, $export->addFilters($map));
        $this->assertNotFalse(has_filter('woocommerce_admin_pk-test_report_export_column_names', [$export, 'addColumns']));
        $this->assertNotFalse(has_filter('woocommerce_export_admin_revenue_report_row_data', [$export, 'addValues']));

        $this->inLanguage('en_US', true);
        (new WooAnalyticsExport())->addFilters(['orders' => 'Orders']);
        $this->assertFalse(has_filter('woocommerce_admin_orders_report_export_column_names'));
    }
}
