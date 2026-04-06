<?php

namespace PersianKit\Tests\Unit\WooCommerce;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\WooCommerce\WooDateDisplayFilter;
use PHPUnit\Framework\TestCase;

class WooDateDisplayFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\when('apply_filters')->alias(static function ($hook, $value) {
            return $value;
        });
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_adds_date_i18n_filter(): void
    {
        $filter = new WooDateDisplayFilter();
        $filter->register();

        $this->assertTrue(has_filter('date_i18n'));
    }

    public function test_is_woo_date_context_detects_wc_datetime_calls(): void
    {
        $filter = new WooDateDisplayFilter();

        $this->assertTrue($filter->isWooDateContext([
            ['class' => 'WC_DateTime', 'function' => 'date_i18n'],
        ]));

        $this->assertTrue($filter->isWooDateContext([
            ['file' => '/var/www/html/wp-content/plugins/woocommerce/templates/order/tracking.php'],
        ]));

        $this->assertTrue($filter->isWooDateContext([
            ['file' => '/var/www/html/wp-content/plugins/woocommerce/src/Blocks/BlockTypes/OrderConfirmation/Downloads.php'],
        ]));

        $this->assertFalse($filter->isWooDateContext([
            ['class' => 'WP_Date_Query', 'function' => 'build_mysql_datetime'],
        ]));
    }

    public function test_filter_date_i18n_converts_visible_woocommerce_dates_only(): void
    {
        $filter = new class extends WooDateDisplayFilter {
            protected function debugTrace(): array
            {
                return [
                    ['class' => 'WC_DateTime', 'function' => 'date_i18n'],
                ];
            }
        };

        $formatted = $filter->filterDateI18n('Mar 21, 2026', 'M j, Y', strtotime('2026-03-21 00:00:00'), false);

        $this->assertNotSame('Mar 21, 2026', $formatted);
        $this->assertStringContainsString('1405', $formatted);
    }

    public function test_filter_date_i18n_converts_customer_template_dates(): void
    {
        $filter = new class extends WooDateDisplayFilter {
            protected function debugTrace(): array
            {
                return [
                    ['file' => '/var/www/html/wp-content/plugins/woocommerce/templates/emails/email-downloads.php'],
                ];
            }
        };

        $formatted = $filter->filterDateI18n('March 21, 2026', 'F j, Y', strtotime('2026-03-21 00:00:00'), false);

        $this->assertNotSame('March 21, 2026', $formatted);
        $this->assertStringContainsString('1405', $formatted);
    }

    public function test_filter_date_i18n_preserves_machine_formats_and_non_woo_contexts(): void
    {
        $filter = new class extends WooDateDisplayFilter {
            protected function debugTrace(): array
            {
                return [
                    ['class' => 'Some_Other_Class', 'function' => 'render'],
                ];
            }
        };

        $this->assertSame(
            '2026-03-21T00:00:00+00:00',
            $filter->filterDateI18n('2026-03-21T00:00:00+00:00', DATE_RFC3339, strtotime('2026-03-21 00:00:00'), true)
        );

        $this->assertSame(
            'Mar 21, 2026',
            $filter->filterDateI18n('Mar 21, 2026', 'M j, Y', strtotime('2026-03-21 00:00:00'), false)
        );
    }
}
