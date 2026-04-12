<?php

namespace PersianKit\Tests\Unit\WooCommerce;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\WooCommerce\WooOrderMonthFilter;
use PHPUnit\Framework\TestCase;

class WooOrderMonthFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('wp_unslash')->returnArg();
    }

    protected function tearDown(): void
    {
        unset($_GET['persian_kit_wc_month']);

        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_filter_order_query_args_returns_original_args_without_selected_month(): void
    {
        $filter = new WooOrderMonthFilter();

        $this->assertSame(['status' => ['wc-processing']], $filter->filterOrderQueryArgs([
            'status' => ['wc-processing'],
        ]));
    }

    public function test_filter_order_query_args_adds_date_created_range_for_selected_jalali_month(): void
    {
        $_GET['persian_kit_wc_month'] = '140501';

        $filter = new WooOrderMonthFilter();
        $args = $filter->filterOrderQueryArgs([
            'status' => ['wc-processing'],
        ]);

        $this->assertSame('2026-03-21...2026-04-20', $args['date_created']);
        $this->assertSame(['wc-processing'], $args['status']);
    }

    public function test_selected_gregorian_range_returns_null_for_invalid_query_value(): void
    {
        $_GET['persian_kit_wc_month'] = '140513';

        $filter = new WooOrderMonthFilter();

        $this->assertNull($filter->selectedGregorianRange());
    }

    public function test_month_options_are_built_from_daynum_backed_jalali_months(): void
    {
        Functions\when('wc_get_orders')->justReturn([
            new class {
                public function get_date_created(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable('2026-03-21 10:00:00', new \DateTimeZone('Asia/Tehran'));
                }
            },
        ]);
        Functions\when('wp_timezone')->justReturn(new \DateTimeZone('Asia/Tehran'));

        $filter = new class extends WooOrderMonthFilter {
            protected function currentDateTime(): \DateTimeInterface
            {
                return new \DateTimeImmutable('2026-04-12 10:00:00', new \DateTimeZone('Asia/Tehran'));
            }

            protected function canQueryOrders(): bool
            {
                return true;
            }
        };

        $this->assertSame([
            [
                'value' => '140501',
                'label' => 'فروردین ۱۴۰۵',
            ],
        ], $filter->monthOptions());
    }
}
