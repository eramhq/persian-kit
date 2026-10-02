<?php

namespace PersianKit\Modules\Seo;

use PersianKit\Modules\WooCommerce\SchemaPrices;

defined('ABSPATH') || exit;

/**
 * Rank Math's product schema and Open Graph price tags, in rials. Rank
 * Math builds its own Product schema in place of WooCommerce's, and its
 * product:price tags carry the store's currency code.
 */
class RankMathPrices
{
    public function register(): void
    {
        // Last, after Rank Math has built and linked every entity.
        add_filter('rank_math/json_ld', [$this, 'filterJsonLd'], PHP_INT_MAX);
        add_filter('rank_math/opengraph/facebook/product_price_amount', [$this, 'filterAmount']);
        add_filter('rank_math/opengraph/facebook/product_price_currency', [$this, 'filterCurrency']);
    }

    public function filterJsonLd(mixed $data): mixed
    {
        return is_array($data) ? SchemaPrices::toRial($data) : $data;
    }

    /**
     * The tag gives no currency of its own, and Rank Math writes the price
     * in the store's, so the amount is converted by that.
     */
    public function filterAmount(mixed $amount): mixed
    {
        $factor = function_exists('get_woocommerce_currency') ? SchemaPrices::factor(get_woocommerce_currency()) : null;

        return $factor === null ? $amount : SchemaPrices::scale($amount, $factor);
    }

    public function filterCurrency(mixed $currency): mixed
    {
        return is_string($currency) && SchemaPrices::factor($currency) !== null ? 'IRR' : $currency;
    }
}
