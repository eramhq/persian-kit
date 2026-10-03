<?php

namespace PersianKit\Tests\Unit\WooCommerce;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Mockery;
use PersianKit\Modules\WooCommerce\CallForPrice;
use PHPUnit\Framework\TestCase;

class CallForPriceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\stubTranslationFunctions();
        Functions\stubEscapeFunctions();
        Functions\when('sanitize_text_field')->alias(static fn (string $text): string => trim(strip_tags($text)));
        Functions\when('wp_strip_all_tags')->alias('strip_tags');
        // Brain Monkey's stubs would add http:// to a relative address.
        Functions\when('esc_url_raw')->returnArg();
        Functions\when('esc_url')->alias(static fn (string $url): string => htmlspecialchars($url, ENT_QUOTES));
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_texts_are_one_line_of_plain_text_at_most_100_characters(): void
    {
        $this->assertSame('تماس بگیرید', CallForPrice::sanitizeText(' <strong>تماس بگیرید</strong> '));
        $this->assertSame(100, mb_strlen(CallForPrice::sanitizeText(str_repeat('ق', 150))));
        $this->assertSame('', CallForPrice::sanitizeText(['nope']));
    }

    public function test_phone_numbers_are_kept_as_typed_in_english_digits(): void
    {
        $this->assertSame('021 1234-5678', CallForPrice::sanitizeLink('۰۲۱ ۱۲۳۴-۵۶۷۸'));
        $this->assertSame('+98 21 1234 5678', CallForPrice::sanitizeLink(' +98 21 1234 5678 '));
        $this->assertSame('1234', CallForPrice::sanitizeLink('١٢٣٤'), 'Arabic digits too');
    }

    public function test_addresses_must_be_http_or_start_with_a_slash(): void
    {
        $this->assertSame('/contact/', CallForPrice::sanitizeLink('/contact/'));
        $this->assertSame('https://example.org/تماس', CallForPrice::sanitizeLink('https://example.org/تماس'));
        $this->assertSame('', CallForPrice::sanitizeLink('javascript:alert(1)'));
        $this->assertSame('', CallForPrice::sanitizeLink('call us'));
        $this->assertSame('', CallForPrice::sanitizeLink('123'), 'too short for a phone number');
        $this->assertSame('', CallForPrice::sanitizeLink(str_repeat('1', 21)), 'too long for one');
        $this->assertSame('', CallForPrice::sanitizeLink('0912+345678'));
        $this->assertSame('', CallForPrice::sanitizeLink(null));
        $this->assertSame('', CallForPrice::sanitizeLink(''));
    }

    public function test_a_phone_number_links_as_tel_with_digits_only(): void
    {
        $this->assertSame('tel:02112345678', CallForPrice::href('021 1234-5678'));
        $this->assertSame('tel:+982112345678', CallForPrice::href('+98 21 1234 5678'));
        $this->assertSame('/contact/', CallForPrice::href('/contact/'));
    }

    public function test_only_the_products_own_page_is_its_page(): void
    {
        Functions\when('is_product')->justReturn(true);
        Functions\when('get_queried_object_id')->justReturn(10);
        $prices = new CallForPrice('Ask us', 'Ask', '');

        $this->assertTrue($prices->isOwnPage($this->product(10)));
        $this->assertTrue($prices->isOwnPage($this->product(11, 10)), 'a variation of the page\'s product');
        $this->assertFalse($prices->isOwnPage($this->product(12)), 'a related product');

        Functions\when('is_product')->justReturn(false);
        $this->assertFalse($prices->isOwnPage($this->product(10)));
    }

    public function test_empty_texts_fall_back_to_the_default_and_the_product_pages(): void
    {
        $this->assertSame('Call for price', (new CallForPrice('', '', ''))->listText());
        $this->assertSame('Ask us', (new CallForPrice('Ask us', '', ''))->listText());
    }

    public function test_the_text_is_escaped_and_links_when_there_is_a_link_outside_lists(): void
    {
        $this->assertSame(
            '<span class="persian-kit-call-for-price">&lt;b&gt;Ask&lt;/b&gt;</span>',
            (new CallForPrice('', '', ''))->html('<b>Ask</b>')
        );
        $this->assertSame(
            '<span class="persian-kit-call-for-price"><a href="tel:+982112345678">Ask</a></span>',
            (new CallForPrice('', '', '+98 21 1234 5678'))->html('Ask')
        );
        $this->assertSame(
            '<span class="persian-kit-call-for-price">Ask</span>',
            (new CallForPrice('', '', '+98 21 1234 5678'))->html('Ask', false),
            'a list'
        );
    }

    public function test_the_button_filters_are_added_only_with_a_link(): void
    {
        Functions\when('is_admin')->justReturn(false);

        $without = new CallForPrice('', '', '');
        $without->register();
        $this->assertNotFalse(has_filter('woocommerce_empty_price_html', [$without, 'filterPriceHtml']));
        $this->assertNotFalse(has_filter('woocommerce_variable_empty_price_html', [$without, 'filterPriceHtml']));
        $this->assertFalse(has_filter('woocommerce_product_add_to_cart_url', [$without, 'filterButtonUrl']));

        $with = new CallForPrice('', '', '/contact/');
        $with->register();
        $this->assertNotFalse(has_filter('woocommerce_product_add_to_cart_url', [$with, 'filterButtonUrl']));
        $this->assertFalse(has_action('admin_init', [$with, 'registerStrings']));
    }

    public function test_custom_texts_are_offered_for_translation(): void
    {
        Functions\expect('pll_register_string')->once()->with('call_for_price_text', 'Ask us', 'Persian Kit');

        (new CallForPrice('Ask us', '', ''))->registerStrings();

        $this->assertSame(1, did_action('wpml_register_single_string'));
    }

    private function product(int $id, int $parentId = 0): \WC_Product
    {
        $product = Mockery::mock(\WC_Product::class);
        $product->shouldReceive('get_id')->andReturn($id);
        $product->shouldReceive('get_parent_id')->andReturn($parentId);

        return $product;
    }
}
