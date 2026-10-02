<?php

namespace PersianKit\Tests\Unit\WooCommerce;

use Brain\Monkey;
use PersianKit\Modules\WooCommerce\CheckoutInputNormalizer;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/functions.php';

class CheckoutInputNormalizerTest extends TestCase
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

    public function test_register_covers_classic_block_and_my_account_forms(): void
    {
        $normalizer = new CheckoutInputNormalizer();
        $normalizer->register();

        $this->assertNotFalse(has_filter('woocommerce_checkout_posted_data', [$normalizer, 'normalizePostedData']));
        $this->assertNotFalse(has_filter('rest_pre_dispatch', [$normalizer, 'normalizeStoreApiRequest']));
        $this->assertNotFalse(has_filter('woocommerce_process_myaccount_field_billing_phone', [$normalizer, 'normalizeDigits']));
        $this->assertNotFalse(has_filter('woocommerce_process_myaccount_field_shipping_postcode', [$normalizer, 'normalizePostcode']));
        $this->assertNotFalse(has_filter('woocommerce_process_myaccount_field_billing_address_1', [$normalizer, 'normalizeText']));
    }

    public function test_classic_checkout_fields_are_mapped_to_their_fix(): void
    {
        $data = (new CheckoutInputNormalizer())->normalizePostedData([
            'billing_first_name'  => 'علي',
            'billing_last_name'   => 'كريمي',
            'billing_company'     => 'شركت',
            'billing_address_1'   => 'خيابان ولي‌عصر، پلاك ۱۲',
            'billing_address_2'   => 'واحد ٣',
            'billing_city'        => 'كرج',
            'billing_phone'       => '۰۹۱۲ ۳۴۵ ۶۷۸۹',
            'billing_postcode'    => '۱۲۳۴۵-۶۷۸۹۰',
            'billing_email'       => 'ali@example.com',
            'billing_country'     => 'IR',
            'shipping_phone'      => '٠٢١٨٨٨٨٧٧٧٧',
            'shipping_postcode'   => '12345 67890',
            'order_comments'      => 'لطفا زنگ نزنيد ۱۲',
        ]);

        $this->assertSame('علی', $data['billing_first_name']);
        $this->assertSame('کریمی', $data['billing_last_name']);
        $this->assertSame('شرکت', $data['billing_company']);
        $this->assertSame('خیابان ولی‌عصر، پلاک ۱۲', $data['billing_address_1'], 'digits in the street address stay Persian');
        $this->assertSame('واحد ۳', $data['billing_address_2']);
        $this->assertSame('کرج', $data['billing_city']);
        $this->assertSame('0912 345 6789', $data['billing_phone'], 'phone keeps its spacing');
        $this->assertSame('1234567890', $data['billing_postcode']);
        $this->assertSame('02188887777', $data['shipping_phone']);
        $this->assertSame('1234567890', $data['shipping_postcode']);
        $this->assertSame('ali@example.com', $data['billing_email']);
        $this->assertSame('IR', $data['billing_country']);
        $this->assertSame('لطفا زنگ نزنيد ۱۲', $data['order_comments'], 'fields it does not know are left alone');
    }

    public function test_store_api_addresses_are_fixed_before_woocommerce_validates_them(): void
    {
        $request = new \WP_REST_Request('POST', '/wc/store/v1/checkout', [
            'billing_address'  => ['phone' => '۰۹۱۲۳۴۵۶۷۸۹', 'postcode' => '۱۲۳۴۵ ۶۷۸۹۰', 'city' => 'كرج', 'country' => 'IR'],
            'shipping_address' => ['first_name' => 'علي'],
            'payment_method'   => 'cod',
        ]);

        $result = (new CheckoutInputNormalizer())->normalizeStoreApiRequest(null, new \WP_REST_Server(), $request);

        $this->assertNull($result);
        $this->assertSame(
            ['phone' => '09123456789', 'postcode' => '1234567890', 'city' => 'کرج', 'country' => 'IR'],
            $request->get_param('billing_address')
        );
        $this->assertSame(['first_name' => 'علی'], $request->get_param('shipping_address'));
        $this->assertSame('cod', $request->get_param('payment_method'));
    }

    public function test_store_api_batch_requests_are_fixed_too(): void
    {
        $request = new \WP_REST_Request('POST', '/wc/store/v1/batch', [
            'requests' => [
                ['path' => '/wc/store/v1/cart/update-customer', 'body' => ['billing_address' => ['phone' => '۰۹۱۲۳۴۵۶۷۸۹']]],
                ['path' => '/wc/store/v1/cart/add-item', 'body' => ['id' => 12]],
            ],
        ]);

        (new CheckoutInputNormalizer())->normalizeStoreApiRequest(null, new \WP_REST_Server(), $request);

        $requests = $request->get_param('requests');
        $this->assertSame('09123456789', $requests[0]['body']['billing_address']['phone']);
        $this->assertSame(['id' => 12], $requests[1]['body']);
    }

    public function test_other_rest_routes_are_left_alone(): void
    {
        $request = new \WP_REST_Request('POST', '/wp/v2/users', ['billing_address' => ['phone' => '۰۹۱۲']]);

        (new CheckoutInputNormalizer())->normalizeStoreApiRequest(null, new \WP_REST_Server(), $request);

        $this->assertSame(['phone' => '۰۹۱۲'], $request->get_param('billing_address'));
    }

    public function test_a_response_already_given_is_passed_on(): void
    {
        $request = new \WP_REST_Request('POST', '/wc/store/v1/checkout', ['billing_address' => ['phone' => '۰۹۱۲']]);

        $this->assertSame('short-circuit', (new CheckoutInputNormalizer())->normalizeStoreApiRequest('short-circuit', new \WP_REST_Server(), $request));
        $this->assertSame(['phone' => '۰۹۱۲'], $request->get_param('billing_address'));
    }

    public function test_non_string_values_pass_through(): void
    {
        $normalizer = new CheckoutInputNormalizer();

        $this->assertNull($normalizer->normalizeDigits(null));
        $this->assertSame('', $normalizer->normalizePostcode(''));
        $this->assertSame(['x'], $normalizer->normalizeText(['x']));
    }
}
