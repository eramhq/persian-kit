<?php

namespace PersianKit\Tests\Unit\WooCommerce;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\WooCommerce\NationalIdField;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/functions.php';

class NationalIdFieldTest extends TestCase
{
    private const VALID_ID = '0499370899';

    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\stubTranslationFunctions();
        Functions\stubEscapeFunctions();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_off_only_shows_ids_already_saved(): void
    {
        $field = new NationalIdField('off');
        $field->register();

        $this->assertNotFalse(has_action('woocommerce_admin_order_data_after_billing_address', [$field, 'renderAdminOrder']));
        $this->assertNotFalse(has_filter('woocommerce_email_customer_details_fields', [$field, 'filterEmailFields']));
        $this->assertFalse(has_filter('woocommerce_checkout_fields', [$field, 'addClassicField']));
        $this->assertFalse(has_action('woocommerce_init', [$field, 'registerBlockField']));
    }

    public function test_on_adds_the_block_and_classic_fields(): void
    {
        $field = new NationalIdField('optional');
        $field->register();

        $this->assertNotFalse(has_action('woocommerce_init', [$field, 'registerBlockField']));
        $this->assertNotFalse(has_filter('woocommerce_checkout_fields', [$field, 'addClassicField']));
        $this->assertNotFalse(has_action('woocommerce_checkout_create_order', [$field, 'saveToOrder']));
    }

    public function test_block_field_is_a_contact_field_required_only_when_set(): void
    {
        $registered = [];
        Functions\when('woocommerce_register_additional_checkout_field')->alias(function (array $options) use (&$registered) {
            $registered[] = $options;
        });

        (new NationalIdField('required'))->registerBlockField();
        (new NationalIdField('optional'))->registerBlockField();

        $this->assertSame(NationalIdField::BLOCK_FIELD_ID, $registered[0]['id']);
        $this->assertSame('contact', $registered[0]['location']);
        $this->assertTrue($registered[0]['required']);
        $this->assertFalse($registered[1]['required']);
        $this->assertSame('_wc_other/persian-kit/national-id', NationalIdField::META_KEY);
    }

    public function test_block_value_is_checked(): void
    {
        $field = new NationalIdField('optional');

        $this->assertTrue($field->validateBlockValue(''));
        $this->assertTrue($field->validateBlockValue('۰۴۹۹۳۷۰۸۹۹'));
        $this->assertInstanceOf(\WP_Error::class, $field->validateBlockValue('0499370890'));
    }

    public function test_classic_field_follows_the_phone_number(): void
    {
        $fields = (new NationalIdField('required'))->addClassicField(['billing' => ['billing_phone' => ['priority' => 100]]]);

        $this->assertTrue($fields['billing'][NationalIdField::CLASSIC_FIELD]['required']);
        $this->assertSame(105, $fields['billing'][NationalIdField::CLASSIC_FIELD]['priority']);
    }

    public function test_classic_checkout_rejects_an_invalid_id(): void
    {
        $field = new NationalIdField('optional');
        $errors = new \WP_Error();

        $field->validateClassicCheckout([NationalIdField::CLASSIC_FIELD => self::VALID_ID], $errors);
        $this->assertFalse($errors->has_errors());

        $field->validateClassicCheckout([NationalIdField::CLASSIC_FIELD => '0499370890'], $errors);
        $this->assertSame([NationalIdField::CLASSIC_FIELD . '_validation'], $errors->get_error_codes());
    }

    public function test_sanitize_keeps_english_digits_only(): void
    {
        $this->assertSame(self::VALID_ID, NationalIdField::sanitize(' ۰۴۹-۹۳۷۰۸۹-۹ '));
        $this->assertSame(self::VALID_ID, NationalIdField::sanitize('٠٤٩ ٩٣٧٠٨٩ ٩'));
        $this->assertSame('', NationalIdField::sanitize(['not', 'scalar']));
    }

    public function test_classic_order_is_saved_under_the_block_meta_key_and_read_back(): void
    {
        $order = new \WC_Order(['created_via' => 'checkout']);
        $order->update_meta_data('_billing_national_id', '۰۴۹۹۳۷۰۸۹۹');

        (new NationalIdField('optional'))->saveToOrder($order, [NationalIdField::CLASSIC_FIELD => '۰۴۹۹۳۷۰۸۹۹']);

        $this->assertSame([NationalIdField::META_KEY => self::VALID_ID], $order->meta);
        $this->assertSame(self::VALID_ID, NationalIdField::get($order));
    }

    public function test_customer_is_saved_under_the_block_meta_key(): void
    {
        $customer = new \WC_Customer();
        $customer->update_meta_data('billing_national_id', self::VALID_ID);

        (new NationalIdField('optional'))->saveToCustomer($customer, [NationalIdField::CLASSIC_FIELD => self::VALID_ID]);

        $this->assertSame([NationalIdField::META_KEY => self::VALID_ID], $customer->meta);
    }

    public function test_orders_without_the_field_are_not_touched(): void
    {
        $order = new \WC_Order();

        (new NationalIdField('optional'))->saveToOrder($order, ['billing_phone' => '0912']);

        $this->assertSame([], $order->meta);
        $this->assertSame('', NationalIdField::get($order));
    }

    public function test_admin_order_shows_the_id_woocommerce_does_not(): void
    {
        $classic = new \WC_Order(['created_via' => 'checkout']);
        $classic->update_meta_data(NationalIdField::META_KEY, self::VALID_ID);
        $block = new \WC_Order(['created_via' => 'store-api']);
        $block->update_meta_data(NationalIdField::META_KEY, self::VALID_ID);

        $field = new NationalIdField('optional');

        $this->assertStringContainsString(self::VALID_ID, $this->render($field, $classic));
        $this->assertSame('', $this->render($field, $block), 'WooCommerce lists the block field itself');
        $this->assertStringContainsString(self::VALID_ID, $this->render(new NationalIdField('off'), $block), 'not registered, so WooCommerce does not list it');
    }

    public function test_email_lists_the_id_woocommerce_does_not(): void
    {
        $classic = new \WC_Order(['created_via' => 'checkout']);
        $classic->update_meta_data(NationalIdField::META_KEY, self::VALID_ID);
        $block = new \WC_Order(['created_via' => 'store-api']);
        $block->update_meta_data(NationalIdField::META_KEY, self::VALID_ID);

        $field = new NationalIdField('required');

        $this->assertSame(
            ['persian_kit_national_id' => ['label' => 'National ID', 'value' => self::VALID_ID]],
            $field->filterEmailFields([], false, $classic)
        );
        $this->assertSame([], $field->filterEmailFields([], false, $block));
        $this->assertSame([], $field->filterEmailFields([], false, new \WC_Order(['created_via' => 'checkout'])));
    }

    private function render(NationalIdField $field, \WC_Order $order): string
    {
        ob_start();
        $field->renderAdminOrder($order);

        return (string) ob_get_clean();
    }
}
