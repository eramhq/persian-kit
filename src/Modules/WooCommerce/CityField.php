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
 * that isn't listed, and nothing is checked against the list. WooCommerce has
 * no supported way to make the block checkout's city a select.
 */
class CityField
{
    /** Copied from resources/data/ by the build. */
    public const DATA_FILE = 'public/data/ir-cities.json';

    private const HANDLE = 'persian-kit-woocommerce-city-select';

    private string $dataFile;

    /** @var array<string, list<string>>|null */
    private ?array $cities = null;

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

        wp_enqueue_script(
            self::HANDLE,
            PERSIAN_KIT_URL . 'public/js/woocommerce-city-select.js',
            [],
            PERSIAN_KIT_VERSION,
            true
        );

        wp_localize_script(self::HANDLE, 'persianKitCities', [
            'cities' => $this->cities(),
        ]);
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
