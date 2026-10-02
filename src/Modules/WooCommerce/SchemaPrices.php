<?php

namespace PersianKit\Modules\WooCommerce;

defined('ABSPATH') || exit;

/**
 * Prices in structured data, in rials. Search engines accept only ISO 4217
 * codes, and Iran's is IRR: toman, thousand toman and thousand rial aren't
 * on the list. Shoppers never see this data, so the prices they read stay
 * in the store's currency.
 *
 * Each node is converted by its own currency, so a USD price from a
 * multi-currency plugin is left alone. The result is in IRR, so running it
 * again (WooCommerce, then an SEO plugin) changes nothing.
 *
 * Opt out with the persian_kit_schema_rial_prices filter, which gets the
 * node's currency code.
 */
class SchemaPrices
{
    /** Schema.org price properties next to priceCurrency. */
    private const PRICE_KEYS = ['price', 'lowPrice', 'highPrice', 'minPrice', 'maxPrice'];

    /** MonetaryAmount's amount properties next to currency, as in shipping rates. */
    private const AMOUNT_KEYS = ['value', 'minValue', 'maxValue'];

    /** An Order's discount, next to discountCurrency. */
    private const DISCOUNT_KEYS = ['discount'];

    public function register(): void
    {
        // Early, so later callbacks, such as Yoast WooCommerce SEO's, get rials.
        add_filter('woocommerce_structured_data_product', [$this, 'filter'], 1);
        // Order markup goes into order emails, for Gmail.
        add_filter('woocommerce_structured_data_order', [$this, 'filter'], 1);
    }

    public function filter(mixed $markup): mixed
    {
        return is_array($markup) ? self::toRial($markup) : $markup;
    }

    /**
     * Converts every price under $node priced in toman, thousand toman or
     * thousand rial to a whole number of rials, as a plain digit string.
     * Prices that aren't numbers, such as an empty one, are kept.
     *
     * @param array<mixed> $node
     * @return array<mixed>
     */
    public static function toRial(array $node): array
    {
        $node = self::convert($node, 'priceCurrency', self::PRICE_KEYS);
        $node = self::convert($node, 'currency', self::AMOUNT_KEYS);
        $node = self::convert($node, 'discountCurrency', self::DISCOUNT_KEYS);

        foreach ($node as $key => $child) {
            if (is_array($child)) {
                $node[$key] = self::toRial($child);
            }
        }

        return $node;
    }

    /**
     * The rials in one unit of $currency when its amounts are to be
     * converted, or null when they are kept: a currency that isn't
     * Iranian, rials already, or a store that opted out.
     */
    public static function factor(string $currency): ?int
    {
        $factor = IranianCurrencies::rialFactor($currency);

        if ($factor === null || $factor === 1 || !apply_filters('persian_kit_schema_rial_prices', true, $currency)) {
            return null;
        }

        return $factor;
    }

    /**
     * $amount times $factor, as a whole number of rials without separators
     * or an exponent. Anything but a number is returned as it is.
     */
    public static function scale(mixed $amount, int $factor): mixed
    {
        if (!is_int($amount) && !is_float($amount) && !(is_string($amount) && is_numeric($amount))) {
            return $amount;
        }

        return number_format(round((float) $amount * $factor), 0, '.', '');
    }

    /**
     * @param array<mixed> $node
     * @param list<string> $amountKeys
     * @return array<mixed>
     */
    private static function convert(array $node, string $currencyKey, array $amountKeys): array
    {
        $currency = $node[$currencyKey] ?? null;
        $factor = is_string($currency) ? self::factor($currency) : null;

        if ($factor === null) {
            return $node;
        }

        foreach ($amountKeys as $key) {
            if (array_key_exists($key, $node)) {
                $node[$key] = self::scale($node[$key], $factor);
            }
        }

        $node[$currencyKey] = IranianCurrencies::RIAL;

        return $node;
    }
}
