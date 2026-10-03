<?php

namespace PersianKit\Modules\WooCommerce;

defined('ABSPATH') || exit;

/**
 * Lists only the provinces a store delivers to, for addresses in Iran.
 *
 * One filter on woocommerce_states covers the block and classic checkout,
 * the Store API's validation, My Account > Addresses and the classic cart's
 * shipping calculator: they all read WC()->countries->get_states(). It
 * applies only to storefront requests, decided from the request itself, so
 * WooCommerce's per-request cache of the list can't keep the wrong one. The
 * shop admin, cron, WP-CLI and REST routes other than the Store API keep all
 * provinces.
 */
class ProvinceLimit
{
    public const COUNTRY = 'IR';

    /** @var list<string> */
    private array $allowed;

    /** @var array<string, string> Every province by code, before trimming. */
    private array $allNames = [];

    private StorefrontRequest $request;

    /**
     * @param list<string> $allowed Province codes, such as THR.
     */
    public function __construct(array $allowed)
    {
        $this->allowed = $allowed;
        $this->request = new StorefrontRequest();
    }

    public function register(): void
    {
        // Late, so names other plugins give the provinces are kept for old addresses.
        add_filter('woocommerce_states', [$this, 'limitStates'], 100);
        add_filter('woocommerce_formatted_address_replacements', [$this, 'restoreStateName'], 10, 2);
        add_filter('woocommerce_customer_get_billing_state', [$this, 'fillSingleProvince'], 10, 2);
        add_filter('woocommerce_customer_get_shipping_state', [$this, 'fillSingleProvince'], 10, 2);
        // WooCommerce doesn't check the province when My Account saves an address.
        add_action('woocommerce_after_save_address_validation', [$this, 'validateSavedAddress'], 10, 4);
    }

    /**
     * Keeps only valid province codes, without duplicates. $known is
     * WooCommerce's list by code; when it is empty, any three-letter code
     * passes.
     *
     * @param array<string, string> $known
     * @return list<string>
     */
    public static function sanitizeCodes(mixed $codes, array $known): array
    {
        if (!is_array($codes)) {
            return [];
        }

        $sanitized = [];
        foreach ($codes as $code) {
            if (!is_string($code)) {
                continue;
            }

            $code = strtoupper(trim($code));
            $valid = $known !== [] ? isset($known[$code]) : preg_match('/^[A-Z]{3}$/', $code) === 1;

            if ($valid && !in_array($code, $sanitized, true)) {
                $sanitized[] = $code;
            }
        }

        return $sanitized;
    }

    public function limitStates(mixed $states): mixed
    {
        if (!is_array($states) || !isset($states[self::COUNTRY]) || !is_array($states[self::COUNTRY])) {
            return $states;
        }

        $this->allNames = $states[self::COUNTRY];

        if (!$this->isStorefrontRequest()) {
            return $states;
        }

        $kept = array_intersect_key($states[self::COUNTRY], array_flip($this->allowed));

        // An empty list would hide the field; keep them all instead.
        if ($kept !== []) {
            $states[self::COUNTRY] = $kept;
        }

        return $states;
    }

    /**
     * A past address in a province no longer listed still shows its name,
     * not its code. $replacements and $args come from WooCommerce's
     * woocommerce_formatted_address_replacements filter.
     */
    public function restoreStateName(mixed $replacements, mixed $args): mixed
    {
        if (!is_array($replacements) || !is_array($args) || ($args['country'] ?? '') !== self::COUNTRY) {
            return $replacements;
        }

        $code = is_string($args['state'] ?? null) ? $args['state'] : '';

        if ($code === '' || !isset($this->allNames[$code]) || ($replacements['{state}'] ?? null) !== $code) {
            return $replacements;
        }

        $replacements['{state}'] = $this->allNames[$code];
        $replacements['{state_upper}'] = function_exists('wc_strtoupper') ? wc_strtoupper($this->allNames[$code]) : $this->allNames[$code];

        return $replacements;
    }

    /**
     * With one province, an Iranian address without one reads as that
     * province on the storefront, so both checkouts start with it selected.
     * That includes a state left from another country. A province no longer
     * listed is kept, so a saved address still shows where it was.
     * WooCommerce saves the customer in the edit context, so nothing stored
     * changes until the customer saves an address.
     */
    public function fillSingleProvince(mixed $value, mixed $customer): mixed
    {
        if (count($this->allowed) !== 1 || !$customer instanceof \WC_Customer) {
            return $value;
        }

        $single = $this->allowed[0];
        if ($this->allNames !== [] && !isset($this->allNames[$single])) {
            return $value;
        }

        if (is_string($value) && $value !== '' && ($this->allNames === [] || isset($this->allNames[$value]))) {
            return $value;
        }

        $type = current_filter() === 'woocommerce_customer_get_shipping_state' ? 'shipping' : 'billing';

        if ($customer->{'get_' . $type . '_country'}() !== self::COUNTRY || !$this->isStorefrontRequest()) {
            return $value;
        }

        return $single;
    }

    /**
     * My Account > Addresses: refuses a province outside the list, as the
     * checkout does. $address holds the address fields.
     */
    public function validateSavedAddress(mixed $userId, mixed $addressType, mixed $address, mixed $customer): void
    {
        if (!is_object($customer) || !in_array($addressType, ['billing', 'shipping'], true) || !function_exists('wc_add_notice')) {
            return;
        }

        $country = (string) $customer->{'get_' . $addressType . '_country'}();
        $state = strtoupper((string) $customer->{'get_' . $addressType . '_state'}());
        $allowed = $this->listedProvinces();

        if ($country !== self::COUNTRY || $state === '' || $allowed === [] || isset($allowed[$state])) {
            return;
        }

        $key = $addressType . '_state';
        $label = is_array($address) && isset($address[$key]['label']) && is_string($address[$key]['label'])
            ? $address[$key]['label']
            : __('Province', 'persian-kit');

        wc_add_notice(
            sprintf(
                /* translators: 1: the province field's label, 2: the provinces the store delivers to. */
                __('%1$s is not valid. Please enter one of the following: %2$s', 'persian-kit'),
                '<strong>' . esc_html($label) . '</strong>',
                esc_html(implode(', ', $allowed))
            ),
            'error',
            ['id' => $key]
        );
    }

    /**
     * Not the admin, cron or WP-CLI, and a REST request only for the Store API.
     */
    public function isStorefrontRequest(): bool
    {
        return $this->request->isStorefront();
    }

    /**
     * The listed provinces by code, as the storefront shows them.
     *
     * @return array<string, string>
     */
    private function listedProvinces(): array
    {
        $states = function_exists('WC') ? WC()->countries->get_states(self::COUNTRY) : false;

        return is_array($states) ? $states : [];
    }
}
