<?php

namespace PersianKit\Modules\WooCommerce;

defined('ABSPATH') || exit;

/**
 * A city dropdown for Iranian addresses in the classic checkout and in My
 * Account > Addresses. A script refills it when the province changes and turns
 * it back into a text input for other countries.
 *
 * The block checkout keeps its free-text city field: WooCommerce has no
 * supported way to make that core field a select.
 */
class CityField
{
    /** Copied from resources/data/ by the build. */
    public const DATA_FILE = 'public/data/ir-cities.json';

    private string $dataFile;

    /** @var array<string, list<string>>|null */
    private ?array $cities = null;

    public function __construct(string $dataFile)
    {
        $this->dataFile = $dataFile;
    }

    public function register(): void
    {
        add_filter('woocommerce_billing_fields', [$this, 'filterBillingFields'], 10, 2);
        add_filter('woocommerce_shipping_fields', [$this, 'filterShippingFields'], 10, 2);
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
    }

    /**
     * Cities by WooCommerce state code (THR, ESF, …).
     *
     * @return array<string, list<string>>
     */
    public function cities(): array
    {
        if ($this->cities !== null) {
            return $this->cities;
        }

        $this->cities = [];

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file shipped with the plugin.
        $json = is_readable($this->dataFile) ? file_get_contents($this->dataFile) : false;
        $data = is_string($json) ? json_decode($json, true) : null;

        if (is_array($data)) {
            foreach ($data as $state => $cities) {
                // Keys starting with "_" hold the source, not a province.
                if (is_string($state) && !str_starts_with($state, '_') && is_array($cities)) {
                    $this->cities[$state] = array_values(array_filter($cities, 'is_string'));
                }
            }
        }

        return $this->cities;
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     * @return array<string, array<string, mixed>>
     */
    public function filterBillingFields(array $fields, mixed $country = ''): array
    {
        return $this->filterFields($fields, 'billing', $country);
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     * @return array<string, array<string, mixed>>
     */
    public function filterShippingFields(array $fields, mixed $country = ''): array
    {
        return $this->filterFields($fields, 'shipping', $country);
    }

    public function enqueue(): void
    {
        if (!$this->isAddressFormPage()) {
            return;
        }

        wp_enqueue_script(
            'persian-kit-woocommerce-city-select',
            PERSIAN_KIT_URL . 'public/js/woocommerce-city-select.js',
            ['jquery'],
            PERSIAN_KIT_VERSION,
            true
        );

        wp_localize_script('persian-kit-woocommerce-city-select', 'persianKitCities', [
            'cities'      => $this->cities(),
            'placeholder' => __('Select a city…', 'persian-kit'),
        ]);
    }

    /**
     * Options for the city select: the province's cities, plus the city
     * already saved when it is not on the list, so it is not lost.
     *
     * @return array<string, string>
     */
    public function options(string $state, string $currentCity = ''): array
    {
        $cities = $this->cities()[$state] ?? [];
        if ($currentCity !== '' && !in_array($currentCity, $cities, true)) {
            $cities[] = $currentCity;
        }

        return ['' => __('Select a city…', 'persian-kit')] + array_combine($cities, $cities);
    }

    /**
     * @param array<string, array<string, mixed>> $fields
     * @return array<string, array<string, mixed>>
     */
    private function filterFields(array $fields, string $group, mixed $country): array
    {
        $key = "{$group}_city";
        if ($country !== 'IR' || !isset($fields[$key]) || (is_admin() && !wp_doing_ajax())) {
            return $fields;
        }

        $fields[$key]['type'] = 'select';
        $fields[$key]['options'] = $this->options($this->currentValue("{$group}_state"), $this->currentValue($key));

        return $fields;
    }

    /**
     * The value the checkout or address form will show: posted, or saved for
     * the customer.
     */
    private function currentValue(string $key): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Read only to pick the dropdown's options; WooCommerce verifies the form.
        if (isset($_POST[$key]) && is_string($_POST[$key])) {
            return sanitize_text_field(wp_unslash($_POST[$key])); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        }

        $customer = self::sessionCustomer();
        if ($customer === null) {
            return '';
        }

        return match ($key) {
            'billing_state'  => $customer->get_billing_state(),
            'billing_city'   => $customer->get_billing_city(),
            'shipping_state' => $customer->get_shipping_state(),
            'shipping_city'  => $customer->get_shipping_city(),
            default          => '',
        };
    }

    private function isAddressFormPage(): bool
    {
        if (!function_exists('is_checkout') || !function_exists('is_wc_endpoint_url')) {
            return false;
        }

        if (is_wc_endpoint_url('edit-address')) {
            return true;
        }

        // The block checkout has no classic city field to change.
        return is_checkout() && !is_wc_endpoint_url() && !has_block('woocommerce/checkout');
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
