<?php

namespace PersianKit\Modules\WooCommerce;

defined('ABSPATH') || exit;

/**
 * Checks Iranian phone numbers and postcodes at checkout and when a customer
 * edits an address in My Account. Applies only to addresses in Iran; an empty
 * field is left to WooCommerce's own required-field check.
 *
 * Each rule can be skipped with the persian_kit_woocommerce_validate filter.
 */
class CheckoutValidator
{
    public function register(): void
    {
        add_action('woocommerce_after_checkout_validation', [$this, 'validateClassicCheckout'], 10, 2);
        add_action('woocommerce_blocks_validate_location_address_fields', [$this, 'validateBlockAddress'], 10, 3);
        add_action('woocommerce_after_save_address_validation', [$this, 'validateAccountAddress'], 10, 4);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function validateClassicCheckout(array $data, \WP_Error $errors): void
    {
        $groups = ['billing'];
        if (!empty($data['ship_to_different_address']) && isset($data['shipping_country'])) {
            $groups[] = 'shipping';
        }

        foreach ($groups as $group) {
            $address = [];
            foreach (['country', 'phone', 'postcode'] as $field) {
                $address[$field] = $data["{$group}_{$field}"] ?? '';
            }

            foreach ($this->addressErrors($address, $group) as $field => $message) {
                $key = "{$group}_{$field}";
                $errors->add($key . '_validation', $message, ['id' => $key]);
            }
        }
    }

    /**
     * @param mixed $fields The address fields, keyed without the group prefix.
     */
    public function validateBlockAddress(\WP_Error $errors, $fields, string $group): void
    {
        if (!is_array($fields) || !in_array($group, ['billing', 'shipping'], true)) {
            return;
        }

        foreach ($this->addressErrors($fields, $group) as $field => $message) {
            $errors->add("persian_kit_invalid_{$group}_{$field}", $message);
        }
    }

    /**
     * My Account > Addresses. The customer already holds the submitted values.
     *
     * @param mixed $address
     */
    public function validateAccountAddress(int $userId, string $addressType, $address, \WC_Customer $customer): void
    {
        if (!in_array($addressType, ['billing', 'shipping'], true)) {
            return;
        }

        $values = $addressType === 'billing'
            ? ['country' => $customer->get_billing_country(), 'phone' => $customer->get_billing_phone(), 'postcode' => $customer->get_billing_postcode()]
            : ['country' => $customer->get_shipping_country(), 'phone' => $customer->get_shipping_phone(), 'postcode' => $customer->get_shipping_postcode()];

        foreach ($this->addressErrors($values, $addressType) as $field => $message) {
            wc_add_notice($message, 'error', ['id' => "{$addressType}_{$field}"]);
        }
    }

    /**
     * @param array<string, mixed> $address Country, phone and postcode, keyed without the group prefix.
     * @return array<string, string> Error message by field.
     */
    public function addressErrors(array $address, string $group): array
    {
        if (strtoupper((string) ($address['country'] ?? '')) !== 'IR') {
            return [];
        }

        $errors = [];

        $phone = $address['phone'] ?? '';
        if (is_string($phone) && $phone !== '' && self::shouldValidate('phone', $group, $phone)
            && !persian_kit_validate_phone($phone)->isValid()
        ) {
            $errors['phone'] = sprintf(
                /* translators: %s: field label, such as "Billing phone". */
                __('%s is not a valid Iranian phone number.', 'persian-kit'),
                $group === 'shipping' ? __('Shipping phone', 'persian-kit') : __('Billing phone', 'persian-kit')
            );
        }

        $postcode = $address['postcode'] ?? '';
        if (is_string($postcode) && $postcode !== '' && self::shouldValidate('postcode', $group, $postcode)
            && !persian_kit_validate_postal_code($postcode)->isValid()
        ) {
            $errors['postcode'] = sprintf(
                /* translators: %s: field label, such as "Billing postcode". */
                __('%s is not a valid Iranian postcode. Postcodes have 10 digits.', 'persian-kit'),
                $group === 'shipping' ? __('Shipping postcode', 'persian-kit') : __('Billing postcode', 'persian-kit')
            );
        }

        return $errors;
    }

    /**
     * Shared by the classic and the block checkout field.
     */
    public static function nationalIdError(string $value): ?string
    {
        if ($value === '' || !self::shouldValidate('national_id', 'billing', $value)
            || persian_kit_validate_national_id($value)->isValid()
        ) {
            return null;
        }

        return __('The national ID is not valid. Check the 10 digits on the national card.', 'persian-kit');
    }

    /**
     * @param string $rule  'phone', 'postcode' or 'national_id'.
     * @param string $group 'billing' or 'shipping'.
     */
    private static function shouldValidate(string $rule, string $group, string $value): bool
    {
        /**
         * Whether to check a checkout value. Return false to skip the rule.
         *
         * @param bool   $validate Default true.
         * @param string $rule     'phone', 'postcode' or 'national_id'.
         * @param string $group    'billing' or 'shipping'.
         * @param string $value    The value as typed.
         */
        return (bool) apply_filters('persian_kit_woocommerce_validate', true, $rule, $group, $value);
    }
}
