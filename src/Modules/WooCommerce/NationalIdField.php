<?php

namespace PersianKit\Modules\WooCommerce;

defined('ABSPATH') || exit;

/**
 * An Iranian national ID (کد ملی) field at checkout.
 *
 * The block checkout gets an additional checkout field, which WooCommerce
 * stores, validates and shows on the order screen, in emails and in My
 * Account. The classic checkout gets a billing field whose value is saved
 * under the same meta key, so get() reads either. WooCommerce shows block
 * fields only on orders placed through the Store API, so this class shows the
 * value on the order screen and in emails for the other orders.
 */
class NationalIdField
{
    public const OFF = 'off';
    public const OPTIONAL = 'optional';
    public const REQUIRED = 'required';
    public const MODES = [self::OFF, self::OPTIONAL, self::REQUIRED];

    public const BLOCK_FIELD_ID = 'persian-kit/national-id';

    /** The meta key WooCommerce gives the block field, on orders and customers. */
    public const META_KEY = '_wc_other/' . self::BLOCK_FIELD_ID;

    public const CLASSIC_FIELD = 'billing_national_id';

    private string $mode;

    public function __construct(string $mode)
    {
        $this->mode = in_array($mode, self::MODES, true) ? $mode : self::OFF;
    }

    public function register(): void
    {
        add_action('woocommerce_admin_order_data_after_billing_address', [$this, 'renderAdminOrder']);
        add_filter('woocommerce_email_customer_details_fields', [$this, 'filterEmailFields'], 10, 3);

        if ($this->mode === self::OFF) {
            return;
        }

        // The plugin boots on after_setup_theme, before WooCommerce fires this on init.
        add_action('woocommerce_init', [$this, 'registerBlockField']);

        add_filter('woocommerce_checkout_fields', [$this, 'addClassicField']);
        add_filter('woocommerce_process_checkout_field_' . self::CLASSIC_FIELD, [self::class, 'sanitize']);
        add_filter('woocommerce_checkout_get_value', [$this, 'filterCheckoutValue'], 10, 2);
        add_action('woocommerce_after_checkout_validation', [$this, 'validateClassicCheckout'], 10, 2);
        add_action('woocommerce_checkout_create_order', [$this, 'saveToOrder'], 10, 2);
        add_action('woocommerce_checkout_update_customer', [$this, 'saveToCustomer'], 10, 2);
    }

    /**
     * The national ID saved on an order, in English digits, or ''.
     */
    public static function get(\WC_Order $order): string
    {
        $value = $order->get_meta(self::META_KEY, true);

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * English digits, without the spaces and dashes of 123-456789-0.
     */
    public static function sanitize(mixed $value): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $value = persian_kit_to_english_digits(trim((string) $value));

        return (string) preg_replace('/[\s\-\x{2010}-\x{2015}\x{2212}]+/u', '', $value);
    }

    public function registerBlockField(): void
    {
        woocommerce_register_additional_checkout_field([
            'id'                => self::BLOCK_FIELD_ID,
            'label'             => __('National ID', 'persian-kit'),
            'optionalLabel'     => __('National ID (optional)', 'persian-kit'),
            'location'          => 'contact',
            'type'              => 'text',
            'required'          => $this->mode === self::REQUIRED,
            'attributes'        => ['autocomplete' => 'off'],
            'sanitize_callback' => [self::class, 'sanitize'],
            'validate_callback' => [$this, 'validateBlockValue'],
        ]);
    }

    /**
     * @return true|\WP_Error
     */
    public function validateBlockValue(mixed $value): bool|\WP_Error
    {
        $error = CheckoutValidator::nationalIdError(self::sanitize($value));

        return $error === null ? true : new \WP_Error('persian_kit_invalid_national_id', $error);
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     * @return array<string, array<string, mixed>>
     */
    public function addClassicField(array $fields): array
    {
        $fields['billing'][self::CLASSIC_FIELD] = [
            'label'             => __('National ID', 'persian-kit'),
            'required'          => $this->mode === self::REQUIRED,
            'type'              => 'text',
            'class'             => ['form-row-wide'],
            'autocomplete'      => 'off',
            'custom_attributes' => ['inputmode' => 'numeric', 'dir' => 'ltr'],
            // Right after the phone number (100), before the email address (110).
            'priority'          => 105,
        ];

        return $fields;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function validateClassicCheckout(array $data, \WP_Error $errors): void
    {
        $error = CheckoutValidator::nationalIdError(self::sanitize($data[self::CLASSIC_FIELD] ?? ''));
        if ($error !== null) {
            $errors->add(self::CLASSIC_FIELD . '_validation', $error, ['id' => self::CLASSIC_FIELD]);
        }
    }

    /**
     * Fills the classic field from the customer, under the block field's key.
     */
    public function filterCheckoutValue(mixed $value, string $input): mixed
    {
        $customer = self::sessionCustomer();
        if ($input !== self::CLASSIC_FIELD || $value !== null || $customer === null) {
            return $value;
        }

        $saved = self::sanitize($customer->get_meta(self::META_KEY, true));

        return $saved === '' ? null : $saved;
    }

    /**
     * WooCommerce saves the classic field as _billing_national_id; keep one key.
     *
     * @param array<string, mixed> $data
     */
    public function saveToOrder(\WC_Order $order, array $data): void
    {
        if (!array_key_exists(self::CLASSIC_FIELD, $data)) {
            return;
        }

        $order->delete_meta_data('_' . self::CLASSIC_FIELD);
        $order->update_meta_data(self::META_KEY, self::sanitize($data[self::CLASSIC_FIELD]));
    }

    /**
     * @param array<string, mixed> $data
     */
    public function saveToCustomer(\WC_Customer $customer, array $data): void
    {
        if (!array_key_exists(self::CLASSIC_FIELD, $data)) {
            return;
        }

        $customer->delete_meta_data(self::CLASSIC_FIELD);
        $customer->update_meta_data(self::META_KEY, self::sanitize($data[self::CLASSIC_FIELD]));
    }

    public function renderAdminOrder(mixed $order): void
    {
        if (!$order instanceof \WC_Order || $this->wooShowsIt($order)) {
            return;
        }

        $value = self::get($order);
        if ($value === '') {
            return;
        }

        printf(
            '<p class="persian-kit-national-id"><strong>%s</strong> <span dir="ltr">%s</span></p>',
            esc_html__('National ID:', 'persian-kit'),
            esc_html($value)
        );
    }

    /**
     * @param array<string, mixed> $fields
     * @return array<string, mixed>
     */
    public function filterEmailFields(array $fields, mixed $sentToAdmin = false, mixed $order = null): array
    {
        if (!$order instanceof \WC_Order || $this->wooShowsIt($order)) {
            return $fields;
        }

        $value = self::get($order);
        if ($value !== '') {
            $fields['persian_kit_national_id'] = [
                'label' => __('National ID', 'persian-kit'),
                'value' => $value,
            ];
        }

        return $fields;
    }

    /**
     * WooCommerce lists a registered block field on orders the Store API created.
     */
    private function wooShowsIt(\WC_Order $order): bool
    {
        return $this->mode !== self::OFF && $order->get_created_via() === 'store-api';
    }

    /**
     * The customer in WooCommerce's session, or null before the session
     * starts (admin screens, cron, most REST requests).
     */
    private static function sessionCustomer(): ?\WC_Customer
    {
        $woocommerce = function_exists('WC') ? WC() : null;

        return $woocommerce->customer ?? null;
    }
}
