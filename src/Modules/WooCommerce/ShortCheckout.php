<?php

namespace PersianKit\Modules\WooCommerce;

defined('ABSPATH') || exit;

/**
 * A shorter checkout when nothing in the cart needs shipping, as for
 * courses, files and services: customers give their name, country, phone
 * and email, and the national ID when it's on. Company, street, city,
 * province and postcode go. Order notes stay.
 *
 * The classic checkout loses the fields through woocommerce_checkout_fields.
 * The block checkout and the Store API's validation read WooCommerce's
 * country locale, where a hidden field is neither shown nor required. The
 * country stays: the block checkout empties hidden fields, and without a
 * country it shows them all again.
 *
 * The block checkout sends the hidden fields empty, and WooCommerce copies
 * the order's address to the customer's account, so the saved address is
 * put back before the order is made and left off it afterwards. Saved
 * addresses in My Account don't change.
 *
 * Whether it applies is decided once per request, after the cart loads, and
 * only for storefront requests.
 */
class ShortCheckout
{
    /** The address fields that go, by WooCommerce's locale key. */
    public const FIELDS = ['company', 'address_1', 'address_2', 'city', 'state', 'postcode'];

    private StorefrontRequest $request;

    private bool $cartLoaded = false;

    private ?bool $applies = null;

    public function __construct(?StorefrontRequest $request = null)
    {
        $this->request = $request ?? new StorefrontRequest();
    }

    public function register(): void
    {
        // Late, after other plugins add or change fields.
        add_filter('woocommerce_checkout_fields', [$this, 'shortenClassicFields'], 100);

        add_filter('woocommerce_get_country_locale', [$this, 'hideLocaleFields'], 100);
        add_filter('woocommerce_get_country_locale_default', [$this, 'hideDefaultLocaleFields'], 100);
        // The block checkout's address card shows a saved address with these.
        add_filter('woocommerce_localisation_address_formats', [$this, 'trimAddressFormats'], 100);
        // WooCommerce keeps the locale and the formats for the rest of the
        // request the first time they are read, which can be before this is
        // decided.
        add_action('woocommerce_cart_loaded_from_session', [$this, 'markCartLoaded'], 100);
        add_action('template_redirect', [$this, 'resetLocaleOnCheckout']);

        // Before the draft order copies the customer's address.
        add_action('woocommerce_store_api_checkout_update_customer_from_request', [$this, 'keepSavedAddress']);
        // After the address is copied back to the customer's account.
        add_action('woocommerce_store_api_checkout_order_processed', [$this, 'clearOrderAddress']);
    }

    /**
     * A storefront request whose cart has items, none of which needs
     * shipping. False until the cart has loaded.
     */
    public function applies(): bool
    {
        if ($this->applies !== null) {
            return $this->applies;
        }

        if (!$this->request->isStorefront()) {
            return $this->applies = false;
        }

        if (!$this->cartLoaded) {
            return false;
        }

        $cart = function_exists('WC') ? WC()->cart : null;

        return $this->applies = $cart instanceof \WC_Cart && self::nothingShips($cart);
    }

    public function markCartLoaded(): void
    {
        $this->cartLoaded = true;

        if ($this->inLocaleScope()) {
            self::resetLocale();
        }
    }

    /**
     * The checkout page is known only once WordPress has read the query.
     */
    public function resetLocaleOnCheckout(): void
    {
        if ($this->inLocaleScope()) {
            self::resetLocale();
        }
    }

    /**
     * Classic checkout: the address fields go.
     */
    public function shortenClassicFields(mixed $fields): mixed
    {
        if (!is_array($fields) || !isset($fields['billing']) || !is_array($fields['billing']) || !$this->applies()) {
            return $fields;
        }

        foreach (self::FIELDS as $key) {
            unset($fields['billing']['billing_' . $key]);
        }

        return $fields;
    }

    /**
     * Block checkout and Store API: each country's address fields are
     * hidden and not required.
     */
    public function hideLocaleFields(mixed $locale): mixed
    {
        if (!is_array($locale) || !$this->inLocaleScope()) {
            return $locale;
        }

        // The block checkout applies only a country's own entry, so each
        // country the store sells to gets one.
        $countries = array_keys(WC()->countries->get_allowed_countries() + WC()->countries->get_shipping_countries());
        foreach ($countries as $country) {
            $fields = $locale[$country] ?? [];
            if (is_array($fields)) {
                $locale[$country] = self::hide($fields, false);
            }
        }

        return $locale;
    }

    /**
     * The fields of countries that have no locale of their own.
     */
    public function hideDefaultLocaleFields(mixed $fields): mixed
    {
        return is_array($fields) && $this->inLocaleScope() ? self::hide($fields, true) : $fields;
    }

    /**
     * Checkout page: the address card shows the name, country and phone, not
     * a saved street the order won't carry. Lines left with no field go.
     * Not in the Store API, whose order emails use the formats.
     *
     * @param mixed $formats Address formats by country, with {field} placeholders.
     */
    public function trimAddressFormats(mixed $formats): mixed
    {
        if (!is_array($formats) || !$this->inLocaleScope() || $this->request->isStoreApi()) {
            return $formats;
        }

        $placeholders = '/\{(?:' . implode('|', array_merge(self::FIELDS, ['state_code'])) . ')(?:_upper)?\}/';

        foreach ($formats as $country => $format) {
            if (!is_string($format)) {
                continue;
            }

            $lines = [];
            foreach (explode("\n", $format) as $line) {
                $line = trim((string) preg_replace($placeholders, '', $line), " ,\u{060C}-");
                if (str_contains($line, '{')) {
                    $lines[] = (string) preg_replace('/(?:\s*,\s*)+/', ', ', $line);
                }
            }
            $formats[$country] = implode("\n", $lines);
        }

        return $formats;
    }

    /**
     * Block checkout: a signed-in customer's saved address takes the place
     * of the empty hidden fields, so copying the order to the account
     * changes nothing.
     */
    public function keepSavedAddress(mixed $customer): void
    {
        if (!$customer instanceof \WC_Customer || $customer->get_id() === 0 || !$this->applies()) {
            return;
        }

        $saved = new \WC_Customer($customer->get_id());

        foreach (['billing', 'shipping'] as $group) {
            foreach (self::FIELDS as $key) {
                $customer->{'set_' . $group . '_' . $key}($saved->{'get_' . $group . '_' . $key}('edit'));
            }
        }
    }

    /**
     * Block checkout: the saved address is left off the order, as in the
     * classic checkout. The country, names, phone and email stay.
     */
    public function clearOrderAddress(mixed $order): void
    {
        if (!$order instanceof \WC_Order || !$this->applies()) {
            return;
        }

        foreach (['billing', 'shipping'] as $group) {
            foreach (self::FIELDS as $key) {
                $order->{'set_' . $group . '_' . $key}('');
            }
        }

        $order->save();
    }

    /**
     * The checkout page or the Store API; never My Account or the admin,
     * where customers keep their whole address.
     */
    private function inLocaleScope(): bool
    {
        if (!$this->applies()) {
            return false;
        }

        if ($this->request->isStoreApi()) {
            return true;
        }

        // The checkout block can sit on any page, not only WooCommerce's own.
        return (function_exists('is_checkout') && is_checkout())
            || (did_action('wp') && is_singular() && has_block('woocommerce/checkout'));
    }

    private static function nothingShips(\WC_Cart $cart): bool
    {
        $items = $cart->get_cart();
        if ($items === []) {
            return false;
        }

        foreach ($items as $item) {
            $product = $item['data'] ?? null;

            // Through WooCommerce's woocommerce_product_needs_shipping filter.
            if (!$product instanceof \WC_Product || $product->needs_shipping()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<mixed> $fields
     * @return array<mixed>
     */
    private static function hide(array $fields, bool $onlyExisting): array
    {
        foreach (self::FIELDS as $key) {
            if ($onlyExisting && !isset($fields[$key])) {
                continue;
            }

            $field = isset($fields[$key]) && is_array($fields[$key]) ? $fields[$key] : [];
            $fields[$key] = array_merge($field, ['hidden' => true, 'required' => false]);
        }

        return $fields;
    }

    private static function resetLocale(): void
    {
        // Called after the cart loads, long after WooCommerce sets up its country list on init.
        if (function_exists('WC')) {
            WC()->countries->locale = [];
            WC()->countries->address_formats = [];
        }
    }
}
