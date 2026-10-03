<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Modules\WooCommerce\CallForPrice;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * Products with no price: a text in place of the price on the product page,
 * in lists, in the price block and in the Store API. Runs when WooCommerce
 * is loaded (see tests/bootstrap.php).
 */
class WooCommerceCallForPriceTest extends WordPressIntegrationTestCase
{
    public function set_up(): void
    {
        parent::set_up();

        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is not loaded.');
        }
    }

    public function test_the_product_page_and_lists_get_their_own_text(): void
    {
        $this->callForPrice('Ask us', 'Ask');
        $product = $this->simpleProduct('');

        $this->assertStringContainsString('<span class="persian-kit-call-for-price">Ask</span>', $product->get_price_html());

        $this->go_to(get_permalink($product->get_id()));
        $this->assertStringContainsString('>Ask us<', $product->get_price_html());
        $this->assertStringContainsString('>Ask<', $this->simpleProduct('')->get_price_html(), 'a related product on that page');
    }

    public function test_without_a_link_the_shop_button_still_reads_read_more(): void
    {
        $this->callForPrice();
        $product = $this->simpleProduct('');

        $this->assertStringContainsString('>Call for price<', $product->get_price_html());
        $this->assertStringNotContainsString('<a ', $product->get_price_html());
        $this->assertSame('Read more', $product->add_to_cart_text());
        $this->assertSame(get_permalink($product->get_id()), $product->add_to_cart_url());
    }

    public function test_a_price_of_0_is_left_alone(): void
    {
        $this->callForPrice();

        $this->assertStringNotContainsString('persian-kit-call-for-price', $this->simpleProduct('0')->get_price_html());
    }

    public function test_a_variable_product_with_no_priced_variation_gets_the_text_and_no_out_of_stock_message(): void
    {
        $this->callForPrice('Ask us');
        $product = $this->variableProduct(['', '']);

        $this->assertStringContainsString('>Ask us<', $product->get_price_html());

        $GLOBALS['product'] = $product;
        ob_start();
        woocommerce_variable_add_to_cart();
        $form = (string) ob_get_clean();
        unset($GLOBALS['product']);

        $this->assertStringContainsString('<p class="stock out-of-stock"></p>', $form);
        $this->assertStringNotContainsString('out of stock and unavailable', $form);
    }

    public function test_a_partly_priced_variable_product_is_left_alone(): void
    {
        $this->callForPrice();
        $product = $this->variableProduct(['', '50']);

        $this->assertStringNotContainsString('persian-kit-call-for-price', $product->get_price_html());
        $this->assertTrue($product->is_purchasable());
    }

    public function test_with_a_phone_number_the_text_and_button_call_it(): void
    {
        $this->callForPrice('', '', '۰۲۱ ۱۲۳۴ ۵۶۷۸');
        $product = $this->simpleProduct('');

        // In lists the button links; the loop prints the price inside the product's link.
        $this->assertSame('<span class="persian-kit-call-for-price">Call for price</span>', $product->get_price_html());
        $this->assertSame('Call for price', $product->add_to_cart_text());
        $this->assertSame('tel:02112345678', $product->add_to_cart_url());
        $this->assertSame('Call for the price of “Tea”', $product->add_to_cart_description());

        $priced = $this->simpleProduct('120');
        $this->assertSame('Add to cart', $priced->add_to_cart_text(), 'a product with a price keeps its button');

        ob_start();
        woocommerce_template_loop_add_to_cart(['quantity' => 1]);
        $GLOBALS['product'] = $product;
        woocommerce_template_loop_add_to_cart(['quantity' => 1]);
        $button = (string) ob_get_clean();
        unset($GLOBALS['product']);
        $this->assertStringContainsString('href="tel:02112345678"', $button);

        $this->go_to(get_permalink($product->get_id()));
        $this->assertStringContainsString('<a href="tel:02112345678">Call for price</a>', $product->get_price_html());
    }

    public function test_an_external_product_keeps_its_button(): void
    {
        $this->callForPrice('', '', '/contact/');
        $product = new \WC_Product_External();
        $product->set_name('Tea');
        $product->set_product_url('https://example.org/tea');
        $product->save();

        $this->assertSame('https://example.org/tea', $product->add_to_cart_url());
        $this->go_to(get_permalink($product->get_id()));
        $this->assertStringContainsString('<a href="/contact/">', $product->get_price_html());
    }

    public function test_the_stores_text_is_translated(): void
    {
        $this->callForPrice('تماس بگیرید');
        add_filter('wpml_translate_single_string', static fn ($text, $group, $name) => $group === 'Persian Kit' && $name === 'call_for_price_text' ? 'Call us' : $text, 10, 3);

        $this->assertStringContainsString('>Call us<', $this->simpleProduct('')->get_price_html());
    }

    public function test_no_sale_badge_without_a_price(): void
    {
        $this->callForPrice();
        $product = new \WC_Product_Simple();
        $product->set_regular_price('100');
        $product->set_sale_price('80');
        $product->set_price('');

        $this->assertTrue($product->is_on_sale('edit'));
        $this->assertFalse($product->is_on_sale());
    }

    public function test_the_price_block_shows_the_list_text(): void
    {
        $this->callForPrice('Ask us', 'Ask');
        $product = $this->simpleProduct('');

        $block = new \WP_Block(
            ['blockName' => 'woocommerce/product-price', 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []],
            ['postId' => $product->get_id()]
        );

        $this->assertStringContainsString('>Ask<', $block->render());
    }

    public function test_the_store_api_gives_the_text_and_the_button(): void
    {
        $this->callForPrice('', 'Ask', '/contact/');
        $product = $this->simpleProduct('');

        $data = rest_do_request(new \WP_REST_Request('GET', '/wc/store/v1/products/' . $product->get_id()))->get_data();

        $this->assertStringContainsString('<span class="persian-kit-call-for-price">Ask</span>', $data['price_html']);
        $this->assertSame('Call for price', $data['add_to_cart']->text);
        $this->assertSame('/contact/', $data['add_to_cart']->url);
    }

    public function test_search_engines_get_no_price_of_0(): void
    {
        $this->callForPrice();
        $product = $this->simpleProduct('');

        $GLOBALS['post'] = get_post($product->get_id());
        $structuredData = new \WC_Structured_Data();
        $structuredData->generate_product_data($product);

        // Without an offer or reviews, WooCommerce leaves the product out.
        $this->assertSame([], $structuredData->get_data());
    }

    public function test_while_the_option_is_off_an_empty_price_stays_empty(): void
    {
        $product = $this->simpleProduct('');

        $this->assertSame('', $product->get_price_html());
        $this->assertSame('Read more', $product->add_to_cart_text());
    }

    public function test_a_link_that_is_neither_a_phone_number_nor_an_address_is_cleared_with_one_notice(): void
    {
        require_once ABSPATH . 'wp-admin/includes/template.php';
        $GLOBALS['wp_settings_errors'] = [];
        delete_option('persian_kit_settings');

        // Sanitized twice as it is first added.
        add_option('persian_kit_settings', ['woocommerce' => ['call_for_price_link' => 'call us']]);
        $notices = get_settings_errors('persian_kit_settings');
        $GLOBALS['wp_settings_errors'] = [];

        $this->assertSame('', get_option('persian_kit_settings')['woocommerce']['call_for_price_link']);
        $this->assertCount(1, $notices);
        $this->assertSame('warning', $notices[0]['type']);

        update_option('persian_kit_settings', ['woocommerce' => ['call_for_price_link' => '/contact/']]);
        $this->assertSame('/contact/', get_option('persian_kit_settings')['woocommerce']['call_for_price_link']);
        $this->assertSame([], get_settings_errors('persian_kit_settings'));
    }

    private function callForPrice(string $productText = '', string $listText = '', string $link = ''): void
    {
        (new CallForPrice($productText, $listText, CallForPrice::sanitizeLink($link)))->register();
    }

    private function simpleProduct(string $price): \WC_Product
    {
        $product = new \WC_Product_Simple();
        $product->set_name('Tea');
        $product->set_regular_price($price);
        $product->save();

        return wc_get_product($product->get_id());
    }

    /**
     * @param list<string> $prices One variation each.
     */
    private function variableProduct(array $prices): \WC_Product_Variable
    {
        $attribute = new \WC_Product_Attribute();
        $attribute->set_name('Size');
        $attribute->set_options(['Small', 'Large']);
        $attribute->set_variation(true);

        $product = new \WC_Product_Variable();
        $product->set_name('Tea');
        $product->set_attributes([$attribute]);
        $product->save();

        foreach ($prices as $i => $price) {
            $variation = new \WC_Product_Variation();
            $variation->set_parent_id($product->get_id());
            $variation->set_attributes(['size' => $i === 0 ? 'Small' : 'Large']);
            $variation->set_regular_price($price);
            $variation->save();
        }
        \WC_Product_Variable::sync($product->get_id());

        $variable = wc_get_product($product->get_id());
        $this->assertInstanceOf(\WC_Product_Variable::class, $variable);

        return $variable;
    }
}
