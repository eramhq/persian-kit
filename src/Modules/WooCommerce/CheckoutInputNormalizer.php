<?php

namespace PersianKit\Modules\WooCommerce;

defined('ABSPATH') || exit;

/**
 * Fixes what customers type into checkout and address forms: Persian and
 * Arabic digits in phone numbers and postcodes become English digits, and
 * Arabic ي/ك in names and addresses become Persian ی/ک. Applies to every
 * country, since a customer abroad types on the same keyboard.
 *
 * The block checkout is fixed in the Store API request itself, before
 * WooCommerce validates the address: its phone and postcode checks reject
 * Persian digits, and they run before any Store API checkout hook.
 */
class CheckoutInputNormalizer
{
    /** Address field => the method that fixes it. */
    private const FIELDS = [
        'phone'      => 'normalizeDigits',
        'postcode'   => 'normalizePostcode',
        'first_name' => 'normalizeText',
        'last_name'  => 'normalizeText',
        'company'    => 'normalizeText',
        'address_1'  => 'normalizeText',
        'address_2'  => 'normalizeText',
        'city'       => 'normalizeText',
    ];

    private const ADDRESS_PARAMS = ['billing_address', 'shipping_address'];

    public function register(): void
    {
        add_filter('woocommerce_checkout_posted_data', [$this, 'normalizePostedData']);
        add_filter('rest_pre_dispatch', [$this, 'normalizeStoreApiRequest'], 10, 3);

        // My Account > Addresses.
        foreach (['billing', 'shipping'] as $group) {
            foreach (self::FIELDS as $field => $method) {
                add_filter("woocommerce_process_myaccount_field_{$group}_{$field}", [$this, $method]);
            }
        }
    }

    /**
     * Classic (shortcode) checkout, before WooCommerce validates the data.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function normalizePostedData(array $data): array
    {
        foreach (['billing', 'shipping'] as $group) {
            $data = $this->normalizeAddress($data, $group . '_');
        }

        return $data;
    }

    /**
     * Block checkout: the address in Store API requests, including those sent
     * in a batch (the batch validates its requests before dispatching them).
     *
     * @param mixed $result
     * @return mixed
     */
    public function normalizeStoreApiRequest($result, \WP_REST_Server $server, \WP_REST_Request $request)
    {
        $route = $request->get_route();
        if ($result !== null || !str_starts_with($route, '/wc/store/')) {
            return $result;
        }

        if (preg_match('#^/wc/store(/v1)?/batch/?$#', $route)) {
            $requests = $request->get_param('requests');
            if (is_array($requests)) {
                foreach ($requests as $index => $single) {
                    if (is_array($single) && isset($single['body']) && is_array($single['body'])) {
                        $requests[$index]['body'] = $this->normalizeStoreApiParams($single['body']);
                    }
                }
                $request->set_param('requests', $requests);
            }

            return $result;
        }

        foreach (self::ADDRESS_PARAMS as $param) {
            $address = $request->get_param($param);
            if (is_array($address)) {
                $request->set_param($param, $this->normalizeAddress($address));
            }
        }

        return $result;
    }

    public function normalizeDigits(mixed $value): mixed
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        return persian_kit_to_english_digits($value);
    }

    /**
     * Postcodes are also written 12345-67890 or with spaces; WooCommerce and
     * shipping plugins expect the 10 digits alone.
     */
    public function normalizePostcode(mixed $value): mixed
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        return (string) preg_replace('/[\s\-\x{2010}-\x{2015}\x{2212}]+/u', '', persian_kit_to_english_digits($value));
    }

    public function normalizeText(mixed $value): mixed
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        return persian_kit_normalize_persian($value);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function normalizeStoreApiParams(array $params): array
    {
        foreach (self::ADDRESS_PARAMS as $param) {
            if (isset($params[$param]) && is_array($params[$param])) {
                $params[$param] = $this->normalizeAddress($params[$param]);
            }
        }

        return $params;
    }

    /**
     * @param array<string, mixed> $address
     * @return array<string, mixed>
     */
    private function normalizeAddress(array $address, string $prefix = ''): array
    {
        foreach (self::FIELDS as $field => $method) {
            $key = $prefix . $field;
            if (array_key_exists($key, $address)) {
                $address[$key] = $this->$method($address[$key]);
            }
        }

        return $address;
    }
}
