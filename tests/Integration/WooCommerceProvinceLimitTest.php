<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Modules\WooCommerce\ProvinceLimit;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * A store that delivers only to Tehran and Alborz, on storefront requests,
 * through WooCommerce's own checks. Runs when WooCommerce is loaded (see
 * tests/bootstrap.php).
 */
class WooCommerceProvinceLimitTest extends WordPressIntegrationTestCase
{
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
        wc_clear_notices();
    }

    public function tear_down(): void
    {
        $_POST = [];
        $_REQUEST = [];
        $_SERVER['REQUEST_URI'] = $this->requestUri;
        unset($GLOBALS['wp']->query_vars['edit-address']);
        wc_clear_notices();
        set_current_screen('front');
        parent::tear_down();

        // Drop the trimmed list WooCommerce cached during the test.
        WC()->countries = new \WC_Countries();
    }

    public function test_the_storefront_lists_only_the_chosen_provinces(): void
    {
        $this->limitTo(['THR', 'ABZ']);

        $this->assertSame(['THR', 'ABZ'], array_keys(WC()->countries->get_states('IR')));
        $this->assertNotEmpty(WC()->countries->get_states('DE'), 'other countries keep theirs');
    }

    public function test_the_admin_still_lists_every_province(): void
    {
        set_current_screen('dashboard');
        $this->limitTo(['THR', 'ABZ']);

        $this->assertCount(31, WC()->countries->get_states('IR'));
    }

    public function test_classic_checkout_refuses_a_province_outside_the_list(): void
    {
        $this->limitTo(['THR', 'ABZ']);

        $this->assertNotContains('billing_state_validation', $this->classicCheckoutErrors('ABZ'));

        $errors = $this->classicCheckoutErrors('ESF');
        $this->assertArrayHasKey('billing_state_validation', $errors);
        $this->assertStringContainsString('Please enter one of the following', $errors['billing_state_validation']);
    }

    public function test_my_account_refuses_a_province_outside_the_list(): void
    {
        $userId = self::factory()->user->create(['role' => 'customer']);
        wp_set_current_user($userId);
        $this->limitTo(['THR', 'ABZ']);

        $this->assertFalse($this->saveBillingAddress('ESF'));
        $this->assertStringContainsString('is not valid. Please enter one of the following:', implode(' | ', $this->errorNotices()));
        $this->assertSame('', (new \WC_Customer($userId))->get_billing_state('edit'), 'nothing is saved');

        wc_clear_notices();
        $this->assertTrue($this->saveBillingAddress('ABZ'), implode(' | ', $this->errorNotices()));
        $this->assertSame('ABZ', (new \WC_Customer($userId))->get_billing_state('edit'));
    }

    public function test_store_api_checkout_refuses_a_province_outside_the_list(): void
    {
        $_SERVER['REQUEST_URI'] = '/wp-json/wc/store/v1/checkout';
        $this->limitTo(['THR', 'ABZ']);
        add_filter('woocommerce_store_api_disable_nonce_check', '__return_true');

        $request = new \WP_REST_Request('POST', '/wc/store/v1/checkout');
        $request->set_header('Content-Type', 'application/json');
        $request->set_body((string) wp_json_encode([
            'billing_address' => [
                'first_name' => 'Ali',
                'last_name'  => 'Karimi',
                'address_1'  => 'خیابان آزادی',
                'city'       => 'اصفهان',
                'country'    => 'IR',
                'state'      => 'ESF',
                'postcode'   => '1234567891',
                'phone'      => '09123456789',
                'email'      => 'ali@example.org',
            ],
            'payment_method'  => 'cod',
        ]));

        $response = rest_do_request($request);

        $this->assertSame(400, $response->get_status());
        $this->assertStringContainsString('invalid_state', (string) wp_json_encode($response->get_data()));
    }

    public function test_a_past_order_to_a_province_no_longer_listed_shows_its_name(): void
    {
        $order = wc_create_order();
        $order->set_billing_first_name('Ali');
        $order->set_billing_country('IR');
        $order->set_billing_state('ESF');
        $order->save();

        $this->limitTo(['THR']);

        $this->assertArrayNotHasKey('ESF', WC()->countries->get_states('IR'));
        $this->assertStringContainsString('Isfahan', $order->get_formatted_billing_address());
    }

    public function test_with_one_province_an_address_without_one_starts_with_it(): void
    {
        $this->limitTo(['THR']);
        WC()->customer->set_billing_country('IR');
        WC()->customer->set_billing_state('');

        $this->assertSame('THR', WC()->customer->get_billing_state());
        $this->assertSame('THR', WC()->checkout()->get_value('billing_state'));
        $this->assertSame('', WC()->customer->get_billing_state('edit'), 'nothing stored changes');
    }

    /**
     * Registers the limit and drops WooCommerce's cached province list, as a
     * fresh request would.
     *
     * @param list<string> $codes
     */
    private function limitTo(array $codes): void
    {
        (new ProvinceLimit($codes))->register();
        WC()->countries = new \WC_Countries();
    }

    /**
     * Posts the classic checkout form and runs WooCommerce's field checks.
     *
     * @return array<string, string> Error messages by code.
     */
    private function classicCheckoutErrors(string $state): array
    {
        $_POST = [
            'woocommerce-process-checkout-nonce' => wp_create_nonce('woocommerce-process_checkout'),
            'billing_first_name'                 => 'Ali',
            'billing_last_name'                  => 'Karimi',
            'billing_country'                    => 'IR',
            'billing_state'                      => $state,
            'billing_city'                       => 'تهران',
            'billing_address_1'                  => 'خیابان آزادی',
            'billing_postcode'                   => '1234567891',
            'billing_phone'                      => '09123456789',
            'billing_email'                      => 'ali@example.org',
            'payment_method'                     => 'cod',
        ];

        $checkout = WC()->checkout();
        $data = $checkout->get_posted_data();
        $errors = new \WP_Error();

        // WooCommerce checks the province in a protected method.
        $validate = new \ReflectionMethod($checkout, 'validate_posted_data');
        $validate->invokeArgs($checkout, [&$data, &$errors]);

        $messages = [];
        foreach ($errors->get_error_codes() as $code) {
            $messages[(string) $code] = $errors->get_error_message($code);
        }

        return $messages;
    }

    /**
     * Posts the My Account billing address form.
     *
     * @return bool Whether WooCommerce saved the address (it redirects only then).
     */
    private function saveBillingAddress(string $state): bool
    {
        $_POST = [
            'action'                         => 'edit_address',
            'woocommerce-edit-address-nonce' => wp_create_nonce('woocommerce-edit_address'),
            'billing_first_name'             => 'Ali',
            'billing_last_name'              => 'Karimi',
            'billing_country'                => 'IR',
            'billing_state'                  => $state,
            'billing_city'                   => 'تهران',
            'billing_address_1'              => 'خیابان آزادی',
            'billing_postcode'               => '1234567891',
            'billing_phone'                  => '09123456789',
            'billing_email'                  => 'ali@example.org',
        ];
        $_REQUEST = $_POST;
        $GLOBALS['wp']->query_vars['edit-address'] = 'billing';

        // WooCommerce redirects and exits after saving; stop at the redirect instead.
        add_filter('wp_redirect', static function (): void {
            throw new \RuntimeException('saved');
        });

        try {
            \WC_Form_Handler::save_address();
        } catch (\RuntimeException $redirect) {
            return $redirect->getMessage() === 'saved';
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function errorNotices(): array
    {
        return array_values(array_map(
            static fn ($notice): string => is_array($notice) ? (string) $notice['notice'] : (string) $notice,
            wc_get_notices('error')
        ));
    }
}
