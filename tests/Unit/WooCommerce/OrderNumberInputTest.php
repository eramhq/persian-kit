<?php

namespace PersianKit\Tests\Unit\WooCommerce;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\WooCommerce\OrderNumberInput;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/functions.php';

class OrderNumberInputTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_covers_the_tracking_form_on_the_front_end(): void
    {
        Functions\when('is_admin')->justReturn(false);
        $input = new OrderNumberInput();
        $input->register();

        $this->assertSame(1, has_filter('woocommerce_shortcode_order_tracking_order_id', [$input, 'normalizeOrderId']));
        $this->assertFalse(has_filter('woocommerce_order_list_table_prepare_items_query_args'));
        $this->assertFalse(has_filter('woocommerce_shop_order_search_results'));
        $this->assertFalse(has_filter('woocommerce_cot_shop_order_search_results'));
    }

    public function test_register_covers_the_order_search_in_the_admin(): void
    {
        Functions\when('is_admin')->justReturn(true);
        $input = new OrderNumberInput();
        $input->register();

        $this->assertNotFalse(has_filter('woocommerce_order_list_table_prepare_items_query_args', [$input, 'normalizeListTableArgs']));
        $this->assertNotFalse(has_filter('woocommerce_shop_order_search_results', [$input, 'addEnglishDigitResults']));
        $this->assertNotFalse(has_filter('woocommerce_cot_shop_order_search_results', [$input, 'addEnglishDigitResults']));
    }

    public function test_tracking_form_order_id_gets_english_digits(): void
    {
        $input = new OrderNumberInput();

        $this->assertSame('123', $input->normalizeOrderId('۱۲۳'));
        $this->assertSame('#123', $input->normalizeOrderId('#١٢٣'));
        $this->assertSame(123, $input->normalizeOrderId(123));
    }

    public function test_order_list_search_gets_english_digits_and_other_args_are_kept(): void
    {
        $args = (new OrderNumberInput())->normalizeListTableArgs(['s' => 'سفارش ۱۲۳', 'status' => 'wc-processing', 'limit' => 20]);

        $this->assertSame(['s' => 'سفارش 123', 'status' => 'wc-processing', 'limit' => 20], $args);
        $this->assertSame(['status' => 'all'], (new OrderNumberInput())->normalizeListTableArgs(['status' => 'all']));
    }

    public function test_order_search_adds_the_english_digit_results(): void
    {
        $searched = [];
        Functions\when('wc_order_search')->alias(function (string $term) use (&$searched): array {
            $searched[] = $term;

            return [123, 45];
        });
        $input = new OrderNumberInput();

        $this->assertSame([45, 123], $input->addEnglishDigitResults([45], '۱۲۳'));
        $this->assertSame(['123'], $searched);
    }

    public function test_order_search_with_english_digits_is_left_alone(): void
    {
        Functions\expect('wc_order_search')->never();

        $this->assertSame([123], (new OrderNumberInput())->addEnglishDigitResults([123], '123'), 'the nested search ends here');
    }
}
