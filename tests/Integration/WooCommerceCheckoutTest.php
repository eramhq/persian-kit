<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Bootstrap;
use PersianKit\Modules\WooCommerce\NationalIdField;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * The checkouts with the WooCommerce module as the plugin booted it (checkout
 * fixes and checks on by default) and the national ID field optional. Runs
 * when WooCommerce is loaded (see tests/bootstrap.php).
 */
class WooCommerceCheckoutTest extends WordPressIntegrationTestCase
{
    private const NATIONAL_ID = '0499370899';

    public function set_up(): void
    {
        parent::set_up();

        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is not installed next to the plugin.');
        }

        WC()->frontend_includes();
        WC()->initialize_session();
        WC()->initialize_cart();

        // The plugin booted the field off, which only shows saved IDs; swap
        // in an optional one. Hooks are restored after the test.
        $booted = Bootstrap::get(NationalIdField::class);
        remove_action('woocommerce_admin_order_data_after_billing_address', [$booted, 'renderAdminOrder']);
        remove_filter('woocommerce_email_customer_details_fields', [$booted, 'filterEmailFields']);
        (new NationalIdField(NationalIdField::OPTIONAL))->register();
    }

    public function tear_down(): void
    {
        $_POST = [];
        parent::tear_down();
    }

    public function test_classic_checkout_order_is_saved_with_english_digits_and_persian_letters(): void
    {
        $data = $this->postCheckout([
            'billing_first_name'  => 'علي',
            'billing_last_name'   => 'كريمي',
            'billing_phone'       => '۰۹۱۲ ۳۴۵ ۶۷۸۹',
            'billing_postcode'    => '۱۲۳۴۵-۶۷۸۹۱',
            'billing_national_id' => '۰۴۹۹۳۷۰۸۹۹',
        ]);

        $this->assertSame([], $this->checkoutErrors($data));

        $order = wc_get_order(WC()->checkout()->create_order($data));

        $this->assertSame('علی', $order->get_billing_first_name());
        $this->assertSame('کریمی', $order->get_billing_last_name());
        $this->assertSame('0912 345 6789', $order->get_billing_phone());
        $this->assertSame('1234567891', $order->get_billing_postcode());
        $this->assertSame(self::NATIONAL_ID, NationalIdField::get($order));
        $this->assertSame(self::NATIONAL_ID, $order->get_meta(NationalIdField::META_KEY));
        $this->assertSame('', $order->get_meta('_billing_national_id'), 'one meta key only');
    }

    public function test_classic_checkout_rejects_invalid_iranian_numbers(): void
    {
        $data = $this->postCheckout([
            'billing_phone'       => '۰۹۱۲۳',
            'billing_postcode'    => '00000',
            'billing_national_id' => '1111111111',
        ]);

        $errors = $this->checkoutErrors($data);

        $this->assertContains('billing_phone_validation', $errors);
        $this->assertContains('billing_postcode_validation', $errors);
        $this->assertContains('billing_national_id_validation', $errors);
    }

    public function test_classic_checkout_leaves_other_countries_to_woocommerce(): void
    {
        $data = $this->postCheckout([
            'billing_country'  => 'DE',
            'billing_state'    => '',
            'billing_phone'    => '030 1234567',
            'billing_postcode' => '10115',
        ]);

        $this->assertNotContains('billing_phone_validation', $this->checkoutErrors($data));
    }

    public function test_classic_order_national_id_appears_on_the_order_screen_and_in_emails(): void
    {
        $order = wc_create_order(['created_via' => 'checkout']);
        $order->update_meta_data(NationalIdField::META_KEY, self::NATIONAL_ID);
        $order->save();

        ob_start();
        do_action('woocommerce_admin_order_data_after_billing_address', $order);
        $this->assertStringContainsString(self::NATIONAL_ID, (string) ob_get_clean());

        $fields = apply_filters('woocommerce_email_customer_details_fields', [], false, $order);
        $this->assertSame(self::NATIONAL_ID, $fields['persian_kit_national_id']['value']);
    }

    public function test_store_api_accepts_an_address_typed_in_persian_digits(): void
    {
        add_filter('woocommerce_store_api_disable_nonce_check', '__return_true');

        $request = new \WP_REST_Request('POST', '/wc/store/v1/cart/update-customer');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body((string) wp_json_encode([
            'billing_address' => [
                'first_name' => 'علي',
                'country'    => 'IR',
                'state'      => 'THR',
                'city'       => 'تهران',
                'postcode'   => '۱۲۳۴۵۶۷۸۹۱',
                'phone'      => '۰۹۱۲۳۴۵۶۷۸۹',
            ],
        ]));

        $response = rest_do_request($request);

        $this->assertSame(200, $response->get_status(), (string) wp_json_encode($response->get_data()));
        $address = (array) $response->get_data()['billing_address'];
        $this->assertSame('09123456789', $address['phone']);
        $this->assertSame('1234567891', $address['postcode']);
        $this->assertSame('علی', $address['first_name']);
    }

    public function test_store_api_checkout_rejects_an_invalid_iranian_phone(): void
    {
        add_filter('woocommerce_store_api_disable_nonce_check', '__return_true');

        // WooCommerce runs the address checks when the order is placed, not
        // while the customer types.
        $request = new \WP_REST_Request('POST', '/wc/store/v1/checkout');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body((string) wp_json_encode([
            'billing_address' => [
                'first_name' => 'Ali',
                'last_name'  => 'Karimi',
                'address_1'  => 'خیابان آزادی',
                'city'       => 'تهران',
                'country'    => 'IR',
                'state'      => 'THR',
                'postcode'   => '1234567891',
                'phone'      => '۰۹۱۲۳',
                'email'      => 'ali@example.org',
            ],
            'payment_method'  => 'cod',
        ]));

        $response = rest_do_request($request);

        $this->assertSame(400, $response->get_status());
        $this->assertStringContainsString('Billing phone', (string) wp_json_encode($response->get_data(), JSON_UNESCAPED_UNICODE));
    }

    /**
     * Post the classic checkout form and read it back as WooCommerce does.
     *
     * @param array<string, string> $fields
     * @return array<string, mixed>
     */
    private function postCheckout(array $fields): array
    {
        $_POST = array_replace([
            'woocommerce-process-checkout-nonce' => wp_create_nonce('woocommerce-process_checkout'),
            'billing_first_name'                 => 'Ali',
            'billing_last_name'                  => 'Karimi',
            'billing_country'                    => 'IR',
            'billing_state'                      => 'THR',
            'billing_city'                       => 'تهران',
            'billing_address_1'                  => 'خیابان آزادی',
            'billing_postcode'                   => '1234567891',
            'billing_phone'                      => '09123456789',
            'billing_email'                      => 'ali@example.org',
            'payment_method'                     => 'cod',
        ], $fields);

        return WC()->checkout()->get_posted_data();
    }

    /**
     * @param array<string, mixed> $data
     * @return list<string> Error codes.
     */
    private function checkoutErrors(array $data): array
    {
        $errors = new \WP_Error();
        do_action('woocommerce_after_checkout_validation', $data, $errors);

        return $errors->get_error_codes();
    }
}
