<?php

namespace PersianKit\Tests\Integration;

use Automattic\WooCommerce\Blocks\Utils\CartCheckoutUtils;
use PersianKit\Bootstrap;
use PersianKit\Modules\WooCommerce\NationalIdField;
use PersianKit\Modules\WooCommerce\ShortCheckout;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * The shorter checkout for carts where nothing needs shipping, through
 * WooCommerce's own checks, with the plugin's other checkout options as
 * booted (fixes and checks on) and the national ID optional. Runs when
 * WooCommerce is loaded (see tests/bootstrap.php).
 */
class WooCommerceShortCheckoutTest extends WordPressIntegrationTestCase
{
    private const ADDRESS_FIELDS = ['company', 'address_1', 'address_2', 'city', 'state', 'postcode'];

    private string $requestUri = '';

    public function set_up(): void
    {
        parent::set_up();

        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is not loaded.');
        }

        $this->requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $_SERVER['REQUEST_URI'] = '/checkout/';

        WC()->frontend_includes();
        WC()->initialize_session();
        WC()->initialize_cart();
        $this->freshRequest();
        // Other tests leave an address in the session's customer.
        WC()->session->set('customer', null);
        WC()->customer = new \WC_Customer(0, true);

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
        $_SERVER['REQUEST_URI'] = $this->requestUri;
        WC()->cart->empty_cart();
        WC()->customer = new \WC_Customer(0, true);
        wp_set_current_user(0);
        parent::tear_down();

        $this->freshRequest();
    }

    public function test_classic_checkout_for_a_virtual_cart_asks_only_for_contact_details(): void
    {
        $this->cartWith(true);
        $this->shortCheckout();

        $fields = WC()->checkout()->get_checkout_fields();

        foreach (self::ADDRESS_FIELDS as $key) {
            $this->assertArrayNotHasKey('billing_' . $key, $fields['billing']);
        }
        foreach (['billing_first_name', 'billing_last_name', 'billing_country', 'billing_phone', 'billing_email', NationalIdField::CLASSIC_FIELD] as $key) {
            $this->assertArrayHasKey($key, $fields['billing']);
        }
        $this->assertSame('country', $fields['billing']['billing_country']['type']);
        $this->assertArrayHasKey('order_comments', $fields['order'], 'order notes stay');
    }

    public function test_classic_checkout_accepts_an_order_without_an_address(): void
    {
        $this->cartWith(true);
        $this->shortCheckout();

        $data = $this->postCheckout(['billing_national_id' => '0499370899']);

        $this->assertSame([], $this->classicCheckoutErrors($data));
        $this->assertSame('IR', $data['billing_country']);

        $order = wc_get_order(WC()->checkout()->create_order($data));
        $this->assertSame('IR', $order->get_billing_country());
        $this->assertSame('', $order->get_billing_city());
        $this->assertSame('', $order->get_billing_address_1());
        $this->assertSame('0499370899', NationalIdField::get($order));
    }

    public function test_classic_checkout_still_refuses_an_invalid_iranian_phone(): void
    {
        $this->cartWith(true);
        $this->shortCheckout();

        $this->assertArrayHasKey('billing_phone_validation', $this->classicCheckoutErrors($this->postCheckout(['billing_phone' => '۰۹۱۲۳'])));
    }

    /**
     * @dataProvider otherCarts
     */
    public function test_classic_checkout_keeps_the_address_for_other_carts(string $cart): void
    {
        if ($cart === 'physical' || $cart === 'mixed') {
            $this->cartWith(false);
        }
        if ($cart === 'mixed' || $cart === 'off') {
            $this->cartWith(true);
        }
        if ($cart !== 'off') {
            $this->shortCheckout();
        }

        $fields = WC()->checkout()->get_checkout_fields('billing');

        $this->assertArrayHasKey('billing_address_1', $fields);
        $this->assertArrayHasKey('billing_city', $fields);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function otherCarts(): array
    {
        return [
            'a physical item'    => ['physical'],
            'a mixed cart'       => ['mixed'],
            'an empty cart'      => ['empty'],
            'the option off'     => ['off'],
        ];
    }

    public function test_store_api_takes_an_order_with_only_a_name_country_phone_and_email(): void
    {
        $_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/checkout';
        $this->cartWith(true);
        $this->shortCheckout();

        $locale = WC()->countries->get_country_locale();
        // The block checkout applies only a country's own entry, and
        // WooCommerce lists only some countries.
        $countries = array_merge(['default'], array_keys(WC()->countries->get_allowed_countries()));
        $this->assertContains('IR', $countries);
        foreach ($countries as $country) {
            foreach (self::ADDRESS_FIELDS as $key) {
                $this->assertTrue($locale[$country][$key]['hidden'], "$country $key");
                $this->assertFalse($locale[$country][$key]['required'], "$country $key");
            }
        }
        foreach (['first_name', 'country'] as $key) {
            $this->assertTrue($locale['default'][$key]['required'], $key);
            $this->assertArrayNotHasKey('hidden', array_filter($locale['default'][$key]), $key);
        }

        $response = $this->placeBlockOrder([
            'first_name' => 'Ali',
            'last_name'  => 'Karimi',
            'country'    => 'IR',
            'phone'      => '09123456789',
            'email'      => 'ali@example.org',
        ]);

        $this->assertSame(200, $response->get_status(), (string) wp_json_encode($response->get_data(), JSON_UNESCAPED_UNICODE));
        $this->assertSame('IR', wc_get_order($response->get_data()['order_id'])->get_billing_country());
    }

    public function test_store_api_still_wants_the_address_for_a_physical_cart(): void
    {
        $_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/checkout';
        $this->cartWith(false);
        $this->shortCheckout();

        $this->assertFalse(WC()->countries->get_country_locale()['default']['address_1']['hidden'] ?? false);

        $response = $this->placeBlockOrder([
            'first_name' => 'Ali',
            'last_name'  => 'Karimi',
            'address_1'  => '',
            'city'       => '',
            'state'      => '',
            'postcode'   => '',
            'country'    => 'IR',
            'phone'      => '09123456789',
            'email'      => 'ali@example.org',
        ]);

        $this->assertSame(400, $response->get_status());
        $this->assertStringContainsString('is required', (string) wp_json_encode($response->get_data()));
    }

    public function test_block_order_leaves_the_saved_address_off_the_order_and_in_the_account(): void
    {
        $_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/checkout';
        $address = [
            'first_name' => 'Ali',
            'last_name'  => 'Karimi',
            'company'    => 'Karimi & Co',
            'address_1'  => 'خیابان آزادی',
            'address_2'  => 'واحد ۲',
            'city'       => 'تهران',
            'state'      => 'THR',
            'postcode'   => '1234567891',
            'country'    => 'IR',
            'phone'      => '09123456789',
            'email'      => 'ali@example.org',
        ];
        $userId = $this->customerWithAddress($address);
        $this->cartWith(true);
        $this->shortCheckout();

        // The block checkout sends the hidden fields empty.
        $response = $this->placeBlockOrder(array_merge($address, array_fill_keys(self::ADDRESS_FIELDS, '')));

        $this->assertSame(200, $response->get_status(), (string) wp_json_encode($response->get_data(), JSON_UNESCAPED_UNICODE));
        $order = wc_get_order($response->get_data()['order_id']);
        foreach (self::ADDRESS_FIELDS as $key) {
            $this->assertSame('', $order->{'get_billing_' . $key}(), "billing $key");
            $this->assertSame('', $order->{'get_shipping_' . $key}(), "shipping $key");
        }
        $this->assertSame('IR', $order->get_billing_country());
        $this->assertSame('Ali', $order->get_billing_first_name());
        $this->assertSame('09123456789', $order->get_billing_phone());
        $this->assertSame('ali@example.org', $order->get_billing_email());

        $customer = new \WC_Customer($userId);
        foreach (self::ADDRESS_FIELDS as $key) {
            $this->assertSame($address[$key], $customer->{'get_billing_' . $key}('edit'), "saved billing $key");
            $this->assertSame($address[$key], $customer->{'get_shipping_' . $key}('edit'), "saved shipping $key");
        }
    }

    public function test_store_api_still_refuses_an_invalid_iranian_phone(): void
    {
        $_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/checkout';
        $this->cartWith(true);
        $this->shortCheckout();

        $response = $this->placeBlockOrder([
            'first_name' => 'Ali',
            'last_name'  => 'Karimi',
            'country'    => 'IR',
            'phone'      => '۰۹۱۲۳',
            'email'      => 'ali@example.org',
        ]);

        $this->assertSame(400, $response->get_status());
        $this->assertStringContainsString('Billing phone', (string) wp_json_encode($response->get_data(), JSON_UNESCAPED_UNICODE));
    }

    public function test_my_account_keeps_the_whole_address_with_a_virtual_cart(): void
    {
        $_SERVER['REQUEST_URI'] = '/my-account/edit-address/billing/';
        $this->cartWith(true);
        $short = $this->shortCheckout();

        $this->assertTrue($short->applies());
        $this->assertArrayNotHasKey('hidden', array_filter(WC()->countries->get_country_locale()['default']['address_1']));
        $this->assertTrue(WC()->countries->get_address_fields('IR', 'billing_')['billing_address_1']['required']);
    }

    public function test_the_checkout_page_gets_the_short_locale(): void
    {
        $this->cartWith(true);
        $short = $this->shortCheckout();

        // Read on the way to the page, before WordPress knows it is the checkout.
        $this->assertArrayNotHasKey('hidden', array_filter(WC()->countries->get_country_locale()['default']['city']));

        add_filter('woocommerce_is_checkout', '__return_true');
        $short->resetLocaleOnCheckout();

        $this->assertTrue(WC()->countries->get_country_locale()['default']['city']['hidden']);

        // The block checkout's address card shows a saved address with these.
        $formats = WC()->countries->get_address_formats();
        $this->assertSame("{name}\n{country}", $formats['default']);
        $this->assertSame("{country}\n{name}", $formats['CN'], 'lines with only address fields go');
        $address = WC()->countries->get_formatted_address([
            'first_name' => 'Ali', 'last_name' => 'Karimi', 'company' => 'Karimi & Co', 'address_1' => 'خیابان آزادی',
            'city' => 'تهران', 'state' => 'THR', 'postcode' => '1234567891', 'country' => 'IR',
        ]);
        $this->assertStringContainsString('Ali Karimi', $address);
        $this->assertStringNotContainsString('تهران', $address);
        $this->assertStringNotContainsString('1234567891', $address);
    }

    public function test_store_api_emails_keep_the_address_formats(): void
    {
        $_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/checkout';
        $this->cartWith(true);
        $this->shortCheckout();

        $this->assertStringContainsString('{city}', WC()->countries->get_address_formats()['default']);
    }

    public function test_a_locale_read_before_the_cart_loaded_is_rebuilt(): void
    {
        $_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/checkout';
        $this->cartWith(true);
        $this->shortCheckout(false);

        $this->assertArrayNotHasKey('hidden', array_filter(WC()->countries->get_country_locale()['default']['postcode']));

        do_action('woocommerce_cart_loaded_from_session', WC()->cart);

        $this->assertTrue(WC()->countries->get_country_locale()['default']['postcode']['hidden']);
    }

    /**
     * Registers the shorter checkout and, unless told not to, loads the cart
     * as WooCommerce does on each request.
     */
    private function shortCheckout(bool $loadCart = true): ShortCheckout
    {
        $short = new ShortCheckout();
        $short->register();

        if ($loadCart) {
            do_action('woocommerce_cart_loaded_from_session', WC()->cart);
        }

        return $short;
    }

    /**
     * Adds a free product, so no payment method is needed.
     */
    private function cartWith(bool $virtual): void
    {
        $product = new \WC_Product_Simple();
        $product->set_name($virtual ? 'Course' : 'Book');
        $product->set_regular_price('0');
        $product->set_virtual($virtual);

        WC()->cart->add_to_cart($product->save());
    }

    /**
     * @param array<string, string> $address
     */
    private function customerWithAddress(array $address): int
    {
        $userId = self::factory()->user->create(['role' => 'customer']);
        $customer = new \WC_Customer($userId);
        foreach ($address as $key => $value) {
            $customer->{'set_billing_' . $key}($value);
            if (in_array($key, self::ADDRESS_FIELDS, true)) {
                $customer->{'set_shipping_' . $key}($value);
            }
        }
        $customer->save();

        wp_set_current_user($userId);
        WC()->customer = new \WC_Customer($userId, true);

        return $userId;
    }

    /**
     * @param array<string, string> $address
     */
    private function placeBlockOrder(array $address): \WP_REST_Response
    {
        add_filter('woocommerce_store_api_disable_nonce_check', '__return_true');

        $request = new \WP_REST_Request('POST', '/wc/store/v1/checkout');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body((string) wp_json_encode(['billing_address' => $address]));

        return rest_do_request($request);
    }

    /**
     * Posts the classic checkout form, without an address, and reads it back
     * as WooCommerce does.
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
            'billing_phone'                      => '09123456789',
            'billing_email'                      => 'ali@example.org',
        ], $fields);

        return WC()->checkout()->get_posted_data();
    }

    /**
     * Runs WooCommerce's checks and the plugin's.
     *
     * @param array<string, mixed> $data
     * @return array<string, string> Error messages by code.
     */
    private function classicCheckoutErrors(array $data): array
    {
        $checkout = WC()->checkout();
        $errors = new \WP_Error();

        // WooCommerce checks the fields in a protected method.
        $validate = new \ReflectionMethod($checkout, 'validate_checkout');
        $validate->invokeArgs($checkout, [&$data, &$errors]);

        $messages = [];
        foreach ($errors->get_error_codes() as $code) {
            $messages[(string) $code] = $errors->get_error_message($code);
        }

        return $messages;
    }

    /**
     * Drops what WooCommerce keeps for the request: the checkout fields, the
     * country locale and whether this is the checkout page.
     */
    private function freshRequest(): void
    {
        (new \ReflectionProperty(\WC_Checkout::class, 'fields'))->setValue(WC()->checkout(), null);
        (new \ReflectionProperty(CartCheckoutUtils::class, 'is_checkout_page'))->setValue(null, null);
        WC()->countries = new \WC_Countries();
    }
}
