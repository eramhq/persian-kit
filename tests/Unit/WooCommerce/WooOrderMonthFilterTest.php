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

    public function test_register_targets_hpos_list_table_query_only(): void
    {
        (new WooOrderMonthFilter())->register();

        $this->assertNotFalse(has_filter('woocommerce_order_list_table_prepare_items_query_args'));
        $this->assertFalse(has_filter('woocommerce_order_query_args'));
        $this->assertFalse(has_action('restrict_manage_posts'), 'PostTypeMonthFilter renders the legacy screen select');
        $this->assertFalse(has_action('pre_get_posts'));
    }

    public function test_core_months_drop_down_is_disabled_on_hpos_screen_only(): void
    {
        Functions\when('wc_get_order_types')->justReturn(['shop_order']);
        $filter = new WooOrderMonthFilter();

        $other = new \WP_Screen();
        $other->id = 'edit-post';
        $filter->disableCoreMonthsFilter($other);
        $this->assertFalse(has_filter('woocommerce_shop_order_list_table_disable_months_filter'));

        $hpos = new \WP_Screen();
        $hpos->id = 'woocommerce_page_wc-orders';
        $filter->disableCoreMonthsFilter($hpos);
        $this->assertNotFalse(has_filter('woocommerce_shop_order_list_table_disable_months_filter', '__return_true'));
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

    public function test_selected_gregorian_range_accepts_persian_digits(): void
    {
        $_GET['persian_kit_wc_month'] = '۱۴۰۵۰۱';

        $filter = new WooOrderMonthFilter();

        $this->assertSame([
            'start' => '2026-03-21',
            'end' => '2026-04-20',
        ], $filter->selectedGregorianRange());
    }

    public function test_month_options_are_built_from_daynum_backed_jalali_months(): void
    {
        $filter = $this->buildFilterWithDates('2026-03-21 10:00:00', '2026-04-12 10:00:00');

        $this->assertSame([
            [
                'value' => '140501',
                'label' => 'فروردین ۱۴۰۵',
            ],
        ], $filter->monthOptions());
    }

    public function test_month_options_include_oldest_month_when_current_time_is_earlier_in_same_month(): void
    {
        $filter = $this->buildFilterWithDates('2026-03-25 18:30:00', '2026-04-12 08:00:00');

        $this->assertSame([
            [
                'value' => '140501',
                'label' => 'فروردین ۱۴۰۵',
            ],
        ], $filter->monthOptions());
    }

    private function buildFilterWithDates(string $orderDate, string $currentDate): WooOrderMonthFilter
    {
        $tz = new \DateTimeZone('Asia/Tehran');

        Functions\expect('wc_get_orders')
            ->once()
            ->with(\Mockery::on(static fn (array $args): bool => $args['return'] === 'ids' && $args['limit'] === 1))
            ->andReturn([42]);
        Functions\expect('wc_get_order')->once()->with(42)->andReturn(
            new class($orderDate, $tz) {
                public function __construct(private string $date, private \DateTimeZone $tz) {}

                public function get_date_created(): \DateTimeImmutable
                {
                    return new \DateTimeImmutable($this->date, $this->tz);
                }
            }
        );
        Functions\when('wp_timezone')->justReturn($tz);

        return new class($currentDate, $tz) extends WooOrderMonthFilter {
            public function __construct(private string $date, private \DateTimeZone $tz) {}

            protected function currentDateTime(): \DateTimeInterface
            {
                return new \DateTimeImmutable($this->date, $this->tz);
            }

            protected function canQueryOrders(): bool
            {
                return true;
            }
        };
    }
}
