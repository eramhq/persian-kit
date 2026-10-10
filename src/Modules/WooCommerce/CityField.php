<?php

namespace PersianKit\Modules\WooCommerce;

defined('ABSPATH') || exit;

/**
 * City suggestions for Iranian addresses: a script gives the city field a
 * list of the province's cities, in the block and classic checkout, the
 * classic cart's shipping calculator and My Account > Addresses. The cart
 * block has no address form: it leaves shipping to the checkout.
 *
 * The field stays a text field, so customers can type a village or a city
 * that isn't listed. WooCommerce has no supported way to make the block
 * checkout's city a select. When an address is saved, a city typed another
 * way (قائمشهر for قایم شهر) is saved under its listed name (see CityNames);
 * anything else is kept as typed, and nothing is refused.
 */
class CityField
{
    /** Copied from resources/data/ by the build. */
    public const DATA_FILE = 'public/data/ir-cities.json';

    private const HANDLE = 'persian-kit-woocommerce-city-select';

    private string $dataFile;

    /** @var array<string, list<string>>|null */
    private ?array $cities = null;

    private ?CityNames $names = null;

    private bool $enqueued = false;

    public function __construct(string $dataFile)
    {
        $this->dataFile = $dataFile;
    }

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueOnAddressPages']);
        // The checkout block can sit on any page, not only WooCommerce's own.
        add_filter('render_block_woocommerce/checkout', [$this, 'enqueueWithBlock']);

        // Block checkout: before the draft order copies the customer's address.
        add_action('woocommerce_store_api_checkout_update_customer_from_request', [$this, 'nameCustomerCities']);
        // Classic checkout, after CheckoutInputNormalizer's fixes.
        add_filter('woocommerce_checkout_posted_data', [$this, 'namePostedCities'], 20);
        add_action('woocommerce_after_save_address_validation', [$this, 'nameSavedAddressCity'], 10, 4);
    }

    /**
     * The listed name for a city typed another way, or null to keep it.
     */
    public function officialName(string $country, string $state, string $city): ?string
    {
        $this->names ??= new CityNames($this->cities());

        return $this->names->officialName($country, $state, $city);
    }

    /**
     * Block checkout: the customer's billing and shipping cities, set from
     * the Store API request.
     */
    public function nameCustomerCities(mixed $customer): void
    {
        if ($customer instanceof \WC_Customer) {
            $this->nameCity($customer, 'billing');
            $this->nameCity($customer, 'shipping');
        }
    }

    /**
     * Classic checkout's posted billing and shipping addresses.
     *
     * @param mixed $data
     * @return mixed
     */
    public function namePostedCities($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        foreach (['billing', 'shipping'] as $group) {
            $city = $data[$group . '_city'] ?? null;
            if (!is_string($city) || $city === '') {
                continue;
            }

            $name = $this->officialName((string) ($data[$group . '_country'] ?? ''), (string) ($data[$group . '_state'] ?? ''), $city);
            if ($name !== null) {
                $data[$group . '_city'] = $name;
            }
        }

        return $data;
    }

    /**
     * My Account > Addresses: the customer is saved right after this hook.
     */
    public function nameSavedAddressCity(mixed $userId, mixed $addressType, mixed $address, mixed $customer): void
    {
        if ($customer instanceof \WC_Customer && in_array($addressType, ['billing', 'shipping'], true)) {
            $this->nameCity($customer, $addressType);
        }
    }

    private function nameCity(\WC_Customer $customer, string $group): void
    {
        $city = (string) $customer->{'get_' . $group . '_city'}('edit');
        if ($city === '') {
            return;
        }

        $name = $this->officialName(
            (string) $customer->{'get_' . $group . '_country'}('edit'),
            (string) $customer->{'get_' . $group . '_state'}('edit'),
            $city
        );

        if ($name !== null && $name !== $city) {
            $customer->{'set_' . $group . '_city'}($name);
        }
    }

    /**
     * Cities by WooCommerce state code (THR, ESF, …), after the
     * persian_kit_woocommerce_cities filter.
     *
     * @return array<string, list<string>>
     */
    public function cities(): array
    {
        if ($this->cities !== null) {
            return $this->cities;
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file shipped with the plugin.
        $json = is_readable($this->dataFile) ? file_get_contents($this->dataFile) : false;
        $data = is_string($json) ? json_decode($json, true) : null;

        // Keys starting with "_" hold the source, not a province.
        $cities = is_array($data) ? self::sanitize($data, true) : [];

        $this->cities = self::sanitize(apply_filters('persian_kit_woocommerce_cities', $cities), false);

        return $this->cities;
    }

    public function enqueueOnAddressPages(): void
    {
        if ($this->isAddressFormPage()) {
            $this->enqueue();
        }
    }

    public function enqueueWithBlock(mixed $content): mixed
    {
        $this->enqueue();

        return $content;
    }

    public function enqueue(): void
    {
        if ($this->enqueued) {
            return;
        }
        $this->enqueued = true;

        wp_enqueue_style(
            self::HANDLE,
            PERSIAN_KIT_URL . 'public/css/woocommerce-city-select.css',
            [],
            PERSIAN_KIT_VERSION
        );

        wp_enqueue_script(
            self::HANDLE,
            PERSIAN_KIT_URL . 'public/js/woocommerce-city-select.js',
            [],
            PERSIAN_KIT_VERSION,
            true
        );

        $data = [
            'cities' => $this->cities(),
            'i18n'   => [
                'label'     => __('City suggestions', 'persian-kit'),
                'oneResult' => __('1 city suggested.', 'persian-kit'),
                /* translators: %d: the number of cities in the list. */
                'results'   => __('%d cities suggested.', 'persian-kit'),
                'noResults' => __('No matching city', 'persian-kit'),
            ],
        ];

        // Not wp_localize_script(): it escapes each Persian letter as \uXXXX,
        // which makes the inline list about 2.5 times larger.
        wp_add_inline_script(
            self::HANDLE,
            'var persianKitCities = ' . wp_json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) . ';',
            'before'
        );
    }

    /**
     * String keys with lists of strings; anything else is dropped.
     *
     * @return array<string, list<string>>
     */
    private static function sanitize(mixed $data, bool $skipMeta): array
    {
        if (!is_array($data)) {
            return [];
        }

        $cities = [];
        foreach ($data as $state => $names) {
            if (!is_string($state) || $state === '' || !is_array($names) || ($skipMeta && str_starts_with($state, '_'))) {
                continue;
            }
            $cities[$state] = array_values(array_filter($names, static fn ($name): bool => is_string($name) && $name !== ''));
        }

        return $cities;
    }

    /** The checkout, the classic cart and My Account's address form. */
    private function isAddressFormPage(): bool
    {
        if (!function_exists('is_checkout') || !function_exists('is_cart') || !function_exists('is_wc_endpoint_url')) {
            return false;
        }

        if (is_wc_endpoint_url('edit-address')) {
            return true;
        }

        if (is_cart()) {
            return !has_block('woocommerce/cart');
        }

        return is_checkout() && !is_wc_endpoint_url();
    }
}
