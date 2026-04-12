<?php

namespace PersianKit\Tests\Unit\WooCommerce;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\WooCommerce\WooPostedDateNormalizer;
use PHPUnit\Framework\TestCase;

class WooPostedDateNormalizerTest extends TestCase
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
        unset(
            $_POST['_sale_price_dates_from'],
            $_POST['_sale_price_dates_to'],
            $_POST['expiry_date'],
            $_POST['order_date'],
            $_POST['order_date_hour'],
            $_POST['order_date_minute'],
            $_POST['order_date_second'],
            $_POST['access_expires'],
            $_POST['variable_sale_price_dates_from'],
            $_POST['variable_sale_price_dates_to']
        );

        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_adds_woocommerce_hooks(): void
    {
        $normalizer = new WooPostedDateNormalizer();
        $normalizer->register();

        $this->assertTrue(has_action('woocommerce_process_product_meta'));
        $this->assertTrue(has_action('woocommerce_process_shop_coupon_meta'));
        $this->assertTrue(has_action('woocommerce_process_shop_order_meta'));
        $this->assertTrue(has_action('wp_ajax_woocommerce_save_variations'));
        $this->assertTrue(has_filter('woocommerce_date_input_html_pattern'));
    }

    public function test_normalize_product_dates_converts_jalali_inputs(): void
    {
        $_POST['_sale_price_dates_from'] = '1405-01-01';
        $_POST['_sale_price_dates_to'] = '۱۴۰۵-۰۱-۳۱';

        (new WooPostedDateNormalizer())->normalizeProductDates();

        $this->assertSame('2026-03-21', $_POST['_sale_price_dates_from']);
        $this->assertSame('2026-04-20', $_POST['_sale_price_dates_to']);
    }

    public function test_normalize_coupon_and_order_dates_converts_jalali_and_digits(): void
    {
        $_POST['expiry_date'] = '1405-02-10';
        $_POST['order_date'] = '۱۴۰۵-۰۲-۱۵';
        $_POST['order_date_hour'] = '۰۹';
        $_POST['order_date_minute'] = '۳۰';
        $_POST['order_date_second'] = '۰۵';

        $normalizer = new WooPostedDateNormalizer();
        $normalizer->normalizeCouponDates();
        $normalizer->normalizeOrderDates();

        $this->assertSame('2026-04-30', $_POST['expiry_date']);
        $this->assertSame('2026-05-05', $_POST['order_date']);
        $this->assertSame('09', $_POST['order_date_hour']);
        $this->assertSame('30', $_POST['order_date_minute']);
        $this->assertSame('05', $_POST['order_date_second']);
    }

    public function test_normalize_variation_dates_only_updates_present_rows(): void
    {
        $_POST['variable_sale_price_dates_from'] = [
            0 => '1405-03-01',
            1 => '',
        ];
        $_POST['variable_sale_price_dates_to'] = [
            0 => '۱۴۰۵-۰۳-۳۱',
        ];

        (new WooPostedDateNormalizer())->normalizeVariationDates();

        $this->assertSame('2026-05-22', $_POST['variable_sale_price_dates_from'][0]);
        $this->assertSame('', $_POST['variable_sale_price_dates_from'][1]);
        $this->assertSame('2026-06-21', $_POST['variable_sale_price_dates_to'][0]);
    }

    public function test_normalize_order_download_expiry_dates_converts_each_row(): void
    {
        $_POST['access_expires'] = [
            0 => '۱۴۰۵-۰۴-۱۰',
            1 => '',
            2 => '2026-07-10',
        ];

        (new WooPostedDateNormalizer())->normalizeOrderDates();

        $this->assertSame('2026-07-01', $_POST['access_expires'][0]);
        $this->assertSame('', $_POST['access_expires'][1]);
        $this->assertSame('2026-07-10', $_POST['access_expires'][2]);
    }

    public function test_filter_date_input_html_pattern_accepts_ascii_and_localized_digits(): void
    {
        $pattern = (new WooPostedDateNormalizer())->filterDateInputHtmlPattern('[0-9]{4}-(0[1-9]|1[012])-(0[1-9]|1[0-9]|2[0-9]|3[01])');

        $this->assertSame(1, preg_match('/^' . $pattern . '$/u', '2026-03-21'));
        $this->assertSame(1, preg_match('/^' . $pattern . '$/u', '۱۴۰۵-۰۱-۰۱'));
        $this->assertSame(1, preg_match('/^' . $pattern . '$/u', '١٤٠٥-٠١-٠١'));
        $this->assertSame(0, preg_match('/^' . $pattern . '$/u', '1405-13-01'));
    }
}
