<?php

namespace PersianKit\Tests\Unit\WooCommerce;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PersianKit\Modules\WooCommerce\CheckoutValidator;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/functions.php';

class CheckoutValidatorTest extends TestCase
{
    private const MOBILE = '09123456789';
    private const LANDLINE = '02188887777';
    private const POSTCODE = '1234567891';

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\stubTranslationFunctions();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_covers_classic_block_and_my_account_forms(): void
    {
        $validator = new CheckoutValidator();
        $validator->register();

        $this->assertNotFalse(has_action('woocommerce_after_checkout_validation', [$validator, 'validateClassicCheckout']));
        $this->assertNotFalse(has_action('woocommerce_blocks_validate_location_address_fields', [$validator, 'validateBlockAddress']));
        $this->assertNotFalse(has_action('woocommerce_after_save_address_validation', [$validator, 'validateAccountAddress']));
    }

    public function test_valid_iranian_mobile_landline_and_postcode_pass(): void
    {
        $validator = new CheckoutValidator();

        $this->assertSame([], $validator->addressErrors(['country' => 'IR', 'phone' => self::MOBILE, 'postcode' => self::POSTCODE], 'billing'));
        $this->assertSame([], $validator->addressErrors(['country' => 'IR', 'phone' => self::LANDLINE, 'postcode' => self::POSTCODE], 'billing'));
        $this->assertSame([], $validator->addressErrors(['country' => 'IR', 'phone' => '+98 912 345 6789'], 'shipping'));
        $this->assertSame([], $validator->addressErrors(['country' => 'IR', 'phone' => '۰۹۱۲۳۴۵۶۷۸۹', 'postcode' => '۱۲۳۴۵۶۷۸۹۱'], 'billing'));
    }

    public function test_invalid_iranian_numbers_are_named_in_the_error(): void
    {
        $errors = (new CheckoutValidator())->addressErrors(['country' => 'IR', 'phone' => '0912345', 'postcode' => '0000012345'], 'shipping');

        $this->assertSame(['phone', 'postcode'], array_keys($errors));
        $this->assertStringContainsString('Shipping phone', $errors['phone']);
        $this->assertStringContainsString('Shipping postcode', $errors['postcode']);
    }

    public function test_addresses_outside_iran_are_not_checked(): void
    {
        $this->assertSame([], (new CheckoutValidator())->addressErrors(['country' => 'DE', 'phone' => '0912345', 'postcode' => '10115'], 'billing'));
    }

    public function test_empty_fields_are_left_to_woocommerce(): void
    {
        $this->assertSame([], (new CheckoutValidator())->addressErrors(['country' => 'IR', 'phone' => '', 'postcode' => ''], 'billing'));
    }

    public function test_the_filter_can_skip_one_rule(): void
    {
        Filters\expectApplied('persian_kit_woocommerce_validate')
            ->andReturnUsing(fn (bool $validate, string $rule) => $rule !== 'postcode');

        $errors = (new CheckoutValidator())->addressErrors(['country' => 'IR', 'phone' => '0912345', 'postcode' => '123'], 'billing');

        $this->assertSame(['phone'], array_keys($errors));
    }

    public function test_classic_checkout_errors_point_at_the_field(): void
    {
        $errors = new \WP_Error();

        (new CheckoutValidator())->validateClassicCheckout([
            'billing_country'           => 'IR',
            'billing_phone'             => '12345',
            'billing_postcode'          => self::POSTCODE,
            'ship_to_different_address' => false,
            'shipping_country'          => 'IR',
            'shipping_phone'            => '12345',
        ], $errors);

        $this->assertSame(['billing_phone_validation'], $errors->get_error_codes(), 'shipping is not checked unless it is used');
        $this->assertSame(['id' => 'billing_phone'], $errors->get_error_data('billing_phone_validation'));
    }

    public function test_classic_checkout_checks_a_separate_shipping_address(): void
    {
        $errors = new \WP_Error();

        (new CheckoutValidator())->validateClassicCheckout([
            'billing_country'           => 'IR',
            'billing_phone'             => self::MOBILE,
            'ship_to_different_address' => true,
            'shipping_country'          => 'IR',
            'shipping_postcode'         => '123',
        ], $errors);

        $this->assertSame(['shipping_postcode_validation'], $errors->get_error_codes());
    }

    public function test_block_checkout_errors_are_added_per_group(): void
    {
        $errors = new \WP_Error();
        $validator = new CheckoutValidator();

        $validator->validateBlockAddress($errors, ['country' => 'IR', 'phone' => '12345', 'postcode' => self::POSTCODE], 'billing');
        $validator->validateBlockAddress($errors, ['country' => 'IR', 'phone' => '12345'], 'other');

        $this->assertSame(['persian_kit_invalid_billing_phone'], $errors->get_error_codes());
    }

    public function test_my_account_address_errors_become_notices(): void
    {
        $notices = [];
        Functions\when('wc_add_notice')->alias(function (string $message, string $type, array $data) use (&$notices) {
            $notices[] = [$type, $data['id']];
        });

        $customer = new \WC_Customer(['billing_country' => 'IR', 'billing_phone' => '12345', 'billing_postcode' => self::POSTCODE]);
        (new CheckoutValidator())->validateAccountAddress(1, 'billing', [], $customer);

        $this->assertSame([['error', 'billing_phone']], $notices);
    }

    public function test_national_id_error_only_for_an_invalid_id(): void
    {
        $this->assertNull(CheckoutValidator::nationalIdError(''));
        $this->assertNull(CheckoutValidator::nationalIdError('0499370899'));
        $this->assertNotNull(CheckoutValidator::nationalIdError('0499370890'));
        $this->assertNotNull(CheckoutValidator::nationalIdError('1111111111'));
    }
}
