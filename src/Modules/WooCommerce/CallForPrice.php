<?php

namespace PersianKit\Modules\WooCommerce;

use PersianKit\Dependencies\Eram\Abzar\Digits\DigitConverter;

defined('ABSPATH') || exit;

/**
 * A text such as «تماس بگیرید» where a product with no price would show an
 * empty space: one text on the product's own page, another in every list
 * (the shop, categories, related products, widgets and block grids).
 *
 * WooCommerce already refuses to sell a product with no price; a price of
 * 0 means free and is left alone. Both filters reach the classic
 * templates, the price blocks and the Store API's price_html. The older
 * "All Products" block draws the price from the Store API's numbers and
 * shows 0.
 *
 * With a link (a phone number or a page address), the text on the product
 * page links to it, and the shop button reads "Call for price" and goes
 * there, in place of "Read more". The text in lists stays plain: the
 * classic loop prints the price inside the link to the product, and a link
 * can't hold another.
 */
class CallForPrice
{
    public const MAX_LENGTH = 100;

    /** The custom texts' group and names, for Polylang and WPML. */
    private const STRING_GROUP = 'Persian Kit';
    private const PRODUCT_TEXT = 'call_for_price_text';
    private const LIST_TEXT = 'call_for_price_list_text';

    private string $productText;

    private string $listText;

    private string $link;

    /**
     * @param string $productText The store's text for the product page, or '' for the default.
     * @param string $listText    The store's text for lists, or '' for the product page's.
     * @param string $link        A sanitized phone number or address (see sanitizeLink()), or ''.
     */
    public function __construct(string $productText, string $listText, string $link)
    {
        $this->productText = $productText;
        $this->listText = $listText;
        $this->link = $link;
    }

    public function register(): void
    {
        // Simple, external and variation products; variable products have their own.
        add_filter('woocommerce_empty_price_html', [$this, 'filterPriceHtml'], 10, 2);
        add_filter('woocommerce_variable_empty_price_html', [$this, 'filterPriceHtml'], 10, 2);
        // A variable product with no priced variation would also read "out of stock".
        add_filter('woocommerce_out_of_stock_message', [$this, 'filterOutOfStockMessage']);
        add_filter('woocommerce_product_is_on_sale', [$this, 'filterOnSale'], 10, 2);

        // The Store API's add_to_cart fields go through the same filters.
        if ($this->link !== '') {
            add_filter('woocommerce_product_add_to_cart_text', [$this, 'filterButtonText'], 10, 2);
            add_filter('woocommerce_product_add_to_cart_url', [$this, 'filterButtonUrl'], 10, 2);
            add_filter('woocommerce_product_add_to_cart_description', [$this, 'filterButtonDescription'], 10, 2);
        }

        // Rank Math's Slack preview would give the price as 0. Its Open
        // Graph price tag and its schema leave an empty price out.
        add_filter('rank_math/opengraph/slack_enhanced_data', [$this, 'filterSlackData']);

        if (is_admin()) {
            add_action('admin_init', [$this, 'registerStrings']);
        }
    }

    public function filterPriceHtml(mixed $html, mixed $product): mixed
    {
        if (!$product instanceof \WC_Product) {
            return $html;
        }

        $ownPage = $this->isOwnPage($product);

        return $this->html($ownPage ? $this->productText() : $this->listText(), $ownPage);
    }

    /**
     * No message for a variable product none of whose variations has a
     * price: the text already shows where its price would be.
     */
    public function filterOutOfStockMessage(mixed $message): mixed
    {
        $product = $GLOBALS['product'] ?? null;

        return $product instanceof \WC_Product_Variable && self::hasNoPrice($product) ? '' : $message;
    }

    /**
     * No sale badge without a price.
     */
    public function filterOnSale(mixed $onSale, mixed $product): mixed
    {
        return $onSale && $product instanceof \WC_Product && self::hasNoPrice($product) ? false : $onSale;
    }

    public function filterButtonText(mixed $text, mixed $product): mixed
    {
        return $this->sendsToLink($product) ? _x('Call for price', 'shop button', 'persian-kit') : $text;
    }

    public function filterButtonUrl(mixed $url, mixed $product): mixed
    {
        return $this->sendsToLink($product) ? self::href($this->link) : $url;
    }

    public function filterButtonDescription(mixed $description, mixed $product): mixed
    {
        if (!$this->sendsToLink($product)) {
            return $description;
        }

        /* translators: %s: product name. */
        return sprintf(__('Call for the price of “%s”', 'persian-kit'), wp_strip_all_tags($product->get_name()));
    }

    /**
     * Rank Math's Slack preview of a product page: the text in place of
     * the price.
     */
    public function filterSlackData(mixed $data): mixed
    {
        $product = is_array($data) && is_singular('product') ? wc_get_product(get_queried_object_id()) : null;
        if (!$product instanceof \WC_Product || !self::hasNoPrice($product)) {
            return $data;
        }

        // Rank Math's product rows are always Price then Availability
        // (get_woo_product_data() in its class-slack.php), with keys in the
        // site's language, so the price is the first row.
        $label = array_key_first($data);
        if ($label !== null) {
            $data[$label] = $this->isOwnPage($product) ? $this->productText() : $this->listText();
        }

        return $data;
    }

    /**
     * Offers the store's own texts for translation.
     */
    public function registerStrings(): void
    {
        foreach ([self::PRODUCT_TEXT => $this->productText, self::LIST_TEXT => $this->listText] as $name => $text) {
            if ($text === '') {
                continue;
            }
            if (function_exists('pll_register_string')) {
                pll_register_string($name, $text, self::STRING_GROUP);
            }
            do_action('wpml_register_single_string', self::STRING_GROUP, $name, $text);
        }
    }

    /**
     * Whether this is the product's own page, which gets the product page's
     * text. Everything else gets the list text, including related products
     * on a product page, the block grids and the Store API.
     */
    public function isOwnPage(\WC_Product $product): bool
    {
        $pageId = $product->get_parent_id() ?: $product->get_id();

        return function_exists('is_product') && is_product() && $pageId === get_queried_object_id();
    }

    /**
     * @param bool $linked Links the text to the link, when there is one.
     */
    public function html(string $text, bool $linked = true): string
    {
        $label = esc_html($text);
        if ($linked && $this->link !== '') {
            $label = sprintf('<a href="%s">%s</a>', esc_url(self::href($this->link), ['tel', 'http', 'https']), $label);
        }

        return '<span class="persian-kit-call-for-price">' . $label . '</span>';
    }

    public function productText(): string
    {
        return $this->productText !== ''
            ? self::translate($this->productText, self::PRODUCT_TEXT)
            : _x('Call for price', 'shown instead of an empty price', 'persian-kit');
    }

    public function listText(): string
    {
        return $this->listText !== '' ? self::translate($this->listText, self::LIST_TEXT) : $this->productText();
    }

    /**
     * One line of plain text, at most MAX_LENGTH characters; '' for the default.
     */
    public static function sanitizeText(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            return '';
        }

        return trim(mb_substr(sanitize_text_field($value), 0, self::MAX_LENGTH));
    }

    /**
     * A phone number (kept as typed, in English digits) or an http(s)
     * address, full or starting with "/"; '' for anything else.
     */
    public static function sanitizeLink(mixed $value): string
    {
        if (!is_string($value) || $value === '') {
            return '';
        }

        // Not sanitize_text_field(): it drops an address's %-encoded letters.
        $link = trim(DigitConverter::toEnglish(wp_strip_all_tags($value)));
        if ($link === '' || self::isPhone($link)) {
            return $link;
        }

        if (!preg_match('#^(https?://|/)#i', $link)) {
            return '';
        }

        return esc_url_raw($link, ['http', 'https']);
    }

    /**
     * A phone number as a tel: address, in digits only; an address as is.
     */
    public static function href(string $link): string
    {
        if (!self::isPhone($link)) {
            return $link;
        }

        return 'tel:' . (str_starts_with($link, '+') ? '+' : '') . preg_replace('/\D+/', '', $link);
    }

    public static function isPhone(string $link): bool
    {
        if (!preg_match('/^\+?[0-9][0-9 -]*$/', $link)) {
            return false;
        }

        $digits = strlen((string) preg_replace('/\D+/', '', $link));

        return $digits >= 4 && $digits <= 20;
    }

    /**
     * Whether the product shows no price: a variable product when none of
     * its variations has one.
     */
    public static function hasNoPrice(\WC_Product $product): bool
    {
        if ($product instanceof \WC_Product_Variable) {
            return empty($product->get_variation_prices()['price']);
        }

        return '' === $product->get_price();
    }

    /**
     * The shop button sends a product WooCommerce won't sell for lack of a
     * price to the link. An external product's button already goes to the
     * store that sells it.
     */
    private function sendsToLink(mixed $product): bool
    {
        return $product instanceof \WC_Product
            && !$product->is_type('external')
            && !$product->is_purchasable()
            && self::hasNoPrice($product);
    }

    private static function translate(string $text, string $name): string
    {
        if (function_exists('pll__')) {
            return (string) pll__($text);
        }

        return (string) apply_filters('wpml_translate_single_string', $text, self::STRING_GROUP, $name);
    }
}
