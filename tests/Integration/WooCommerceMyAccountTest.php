<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Modules\WooCommerce\CityField;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * My Account > Addresses, saved through WooCommerce's own form handler, with
 * the WooCommerce module as the plugin booted it (checkout fixes and checks on
 * by default). Runs when WooCommerce is loaded (see tests/bootstrap.php).
 */
class WooCommerceMyAccountTest extends WordPressIntegrationTestCase
{
    private int $userId;

    public function set_up(): void
    {
        parent::set_up();

        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is not loaded.');
        }

        $this->userId = self::factory()->user->create(['role' => 'customer']);
        wp_set_current_user($this->userId);

        WC()->frontend_includes();
        WC()->initialize_session();
        WC()->initialize_cart();
        wc_clear_notices();
    }

    public function tear_down(): void
    {
        $_POST = [];
        $_REQUEST = [];
        unset($GLOBALS['wp']->query_vars['edit-address']);
        wc_clear_notices();
        parent::tear_down();
    }

    public function test_an_address_typed_with_persian_digits_and_arabic_letters_is_saved_fixed(): void
    {
        $this->assertTrue($this->saveBillingAddress([
            'billing_first_name' => 'علي',
            'billing_last_name'  => 'كريمي',
            'billing_city'       => 'كرج',
            'billing_postcode'   => '۱۲۳۴۵-۶۷۸۹۱',
            'billing_phone'      => '۰۹۱۲ ۳۴۵ ۶۷۸۹',
        ]), implode(' | ', $this->errorNotices()));

        $customer = new \WC_Customer($this->userId);
        $this->assertSame('علی', $customer->get_billing_first_name());
        $this->assertSame('کریمی', $customer->get_billing_last_name());
        $this->assertSame('کرج', $customer->get_billing_city());
        $this->assertSame('1234567891', $customer->get_billing_postcode());
        $this->assertSame('0912 345 6789', $customer->get_billing_phone());
    }

    public function test_an_invalid_iranian_phone_and_postcode_are_refused(): void
    {
        $this->assertFalse($this->saveBillingAddress([
            'billing_phone'    => '۰۹۱۲۳',
            'billing_postcode' => '0000012345',
        ]));

        $errors = $this->errorNotices();
        $this->assertContains('Billing phone is not a valid Iranian phone number.', $errors);
        $this->assertContains('Billing postcode is not a valid Iranian postcode. Postcodes have 10 digits.', $errors);
        $this->assertSame('', (new \WC_Customer($this->userId))->get_billing_phone(), 'nothing is saved');
    }

    public function test_an_address_outside_iran_is_not_checked(): void
    {
        $this->assertTrue($this->saveBillingAddress([
            'billing_country'  => 'DE',
            'billing_state'    => '',
            'billing_postcode' => '10115',
            'billing_phone'    => '030 1234567',
        ]), implode(' | ', $this->errorNotices()));
    }

    public function test_the_city_stays_a_text_field_that_takes_an_unlisted_place(): void
    {
        $this->cityField()->register();

        $this->assertSame('text', WC()->countries->get_address_fields('IR', 'billing_')['billing_city']['type'] ?? 'text');

        $this->assertTrue($this->saveBillingAddress([
            'billing_state' => 'QHM',
            'billing_city'  => 'روستای من',
        ]), implode(' | ', $this->errorNotices()));
        $this->assertSame('روستای من', (new \WC_Customer($this->userId))->get_billing_city());
    }

    public function test_a_city_typed_another_way_is_saved_under_its_listed_name(): void
    {
        $this->cityField()->register();

        $this->assertTrue($this->saveBillingAddress([
            'billing_state' => 'MZN',
            'billing_city'  => 'قائمشهر',
        ]), implode(' | ', $this->errorNotices()));
        $this->assertSame('قایم شهر', (new \WC_Customer($this->userId))->get_billing_city());
    }

    public function test_the_checkout_block_loads_the_city_list_once_on_any_page(): void
    {
        $this->cityField()->register();
        $handle = 'persian-kit-woocommerce-city-select';
        $this->assertFalse(wp_script_is($handle, 'enqueued'));

        try {
            $this->assertSame('<div>checkout</div>', apply_filters('render_block_woocommerce/checkout', '<div>checkout</div>', [], null));
            apply_filters('render_block_woocommerce/checkout', '', [], null);

            $this->assertTrue(wp_script_is($handle, 'enqueued'));
            $data = (string) wp_scripts()->get_data($handle, 'data');
            $this->assertSame(1, substr_count($data, 'var persianKitCities'));
            $this->assertStringContainsString('"THR":["\u062a\u0647\u0631\u0627\u0646"', $data);
            $this->assertStringContainsString('"noResults":', $data);
            $this->assertTrue(wp_style_is($handle, 'enqueued'));
        } finally {
            wp_dequeue_script($handle);
            wp_deregister_script($handle);
            wp_dequeue_style($handle);
            wp_deregister_style($handle);
        }
    }

    private function cityField(): CityField
    {
        return new CityField(dirname(__DIR__, 2) . '/resources/data/ir-cities.json');
    }

    /**
     * Post the My Account billing address form.
     *
     * @param array<string, string> $fields
     * @return bool Whether WooCommerce saved the address (it redirects only then).
     */
    private function saveBillingAddress(array $fields): bool
    {
        $_POST = array_replace([
            'action'                         => 'edit_address',
            'woocommerce-edit-address-nonce' => wp_create_nonce('woocommerce-edit_address'),
            'billing_first_name'             => 'Ali',
            'billing_last_name'              => 'Karimi',
            'billing_country'                => 'IR',
            'billing_state'                  => 'THR',
            'billing_city'                   => 'تهران',
            'billing_address_1'              => 'خیابان آزادی',
            'billing_postcode'               => '1234567891',
            'billing_phone'                  => '09123456789',
            'billing_email'                  => 'ali@example.org',
        ], $fields);
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
