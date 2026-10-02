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

    public function test_the_city_is_a_dropdown_of_the_province_cities(): void
    {
        (new CityField(dirname(__DIR__, 2) . '/resources/data/ir-cities.json'))->register();
        WC()->customer->set_billing_state('QHM');
        WC()->customer->set_billing_city('');

        $fields = WC()->countries->get_address_fields('IR', 'billing_');

        $this->assertSame('select', $fields['billing_city']['type']);
        $this->assertSame(['', 'قم', 'جعفریه', 'دستجرد', 'سلفچگان', 'قاهان', 'قنوات', 'کهک'], array_keys($fields['billing_city']['options']));

        // A city saved before, which the province's list lacks, stays selectable.
        WC()->customer->set_billing_city('تهران');
        $this->assertSame('تهران', array_key_last(WC()->countries->get_address_fields('IR', 'billing_')['billing_city']['options']));

        $german = WC()->countries->get_address_fields('DE', 'billing_');
        $this->assertSame('text', $german['billing_city']['type'] ?? 'text');
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
