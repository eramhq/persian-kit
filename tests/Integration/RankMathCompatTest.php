<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Modules\WooCommerce\CallForPrice;
use PersianKit\Tests\Integration\Support\ReadsSeoOutput;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * Rank Math's output for search engines stays valid with Persian Kit
 * converting every date and digit: Gregorian dates and rials, in its
 * schema, Open Graph tags and sitemaps. Runs when Rank Math is loaded (see
 * tests/bootstrap.php).
 */
class RankMathCompatTest extends WordPressIntegrationTestCase
{
    use ReadsSeoOutput;

    public function set_up(): void
    {
        parent::set_up();

        if (!defined('RANK_MATH_VERSION')) {
            $this->markTestSkipped('Rank Math is not loaded.');
        }

        $this->convertEverything();
    }

    public function test_a_posts_schema_and_open_graph_dates_are_gregorian(): void
    {
        $html = $this->head(get_permalink($this->postOnFarvardin16()));

        $dates = $this->valuesOf($this->jsonLd($html, 'rank-math-schema'), ['datePublished', 'dateModified']);
        $this->assertNotEmpty($dates);
        array_map([$this, 'assertMachineDate'], $dates);

        // Yoast SEO, when loaded too, prints its own.
        $published = $this->meta($html, 'article:published_time');
        $this->assertNotEmpty($published);
        foreach ($published as $date) {
            $this->assertStringStartsWith('2026-04-05T', $date);
        }
        array_map([$this, 'assertMachineDate'], array_merge($published, $this->meta($html, 'og:updated_time')));
    }

    public function test_a_products_schema_and_open_graph_prices_are_in_rials(): void
    {
        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is not loaded.');
        }

        $html = $this->head(get_permalink($this->productInThousandToman()));
        $schema = $this->jsonLd($html, 'rank-math-schema');

        $this->assertContains('1200000', $this->valuesOf($schema, ['price']));
        $this->assertSame(['IRR'], array_values(array_unique($this->valuesOf($schema, ['priceCurrency']))));
        $this->assertSame(['1200000'], $this->meta($html, 'product:price:amount'));
        $this->assertSame(['IRR'], $this->meta($html, 'product:price:currency'));
        $this->assertDoesNotMatchRegularExpression('/IRH[TR]/', $html);
    }

    public function test_the_store_can_keep_its_currency(): void
    {
        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is not loaded.');
        }

        add_filter('persian_kit_schema_rial_prices', '__return_false');
        $html = $this->head(get_permalink($this->productInThousandToman()));

        $this->assertSame(['120'], $this->meta($html, 'product:price:amount'));
        $this->assertSame(['IRHT'], $this->meta($html, 'product:price:currency'));
    }

    public function test_a_product_without_a_price_gets_no_price_of_0(): void
    {
        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is not loaded.');
        }

        $product = new \WC_Product_Simple();
        $product->set_name('Tea');
        $product->save();
        (new CallForPrice('Ask us', '', ''))->register();
        $settings = get_option('rank-math-options-titles', []);
        update_option('rank-math-options-titles', array_replace(is_array($settings) ? $settings : [], ['pt_product_slack_enhanced_sharing' => 'on']));
        // Rank Math keeps its settings, and the product of its Open Graph
        // tags, for the request.
        rank_math()->settings->reset();
        foreach ($GLOBALS['wp_filter']['rank_math/opengraph/facebook']->callbacks[50] ?? [] as $callback) {
            if (is_array($callback['function']) && $callback['function'][0] instanceof \RankMath\WooCommerce\Opengraph) {
                \Closure::bind(fn () => $this->product = null, $callback['function'][0], \RankMath\WooCommerce\Base::class)();
            }
        }

        $html = $this->head(get_permalink($product->get_id()));

        $this->assertSame([], $this->meta($html, 'product:price:amount'));
        $this->assertNotContains('0', $this->valuesOf($this->jsonLd($html, 'rank-math-schema'), ['price']));
        $this->assertSame(1, preg_match('#<meta name="twitter:label\d+" content="Price" />\s*<meta name="twitter:data\d+" content="([^"]*)" />#', $html, $match), 'the Slack preview has its price row');
        $this->assertSame('Ask us', $match[1]);
    }

    public function test_sitemap_lastmod_is_gregorian(): void
    {
        $this->postOnFarvardin16();

        $xml = (string) (new \RankMath\Sitemap\Generator())->get_output('post', 1);

        $this->assertGreaterThan(0, preg_match_all('#<lastmod>([^<]+)</lastmod>#', $xml, $matches));
        array_map([$this, 'assertMachineDate'], $matches[1]);
    }

    public function test_only_the_tab_title_gets_persian_digits(): void
    {
        $html = $this->head(get_permalink($this->postTitledTop10()));

        $this->assertOnlyTheTabTitleConverts($html, 'rank-math-schema', ['headline', 'name']);
    }

    public function test_a_jalali_month_archive_is_named_for_its_jalali_month(): void
    {
        $this->postOnFarvardin16();
        $this->go_to(home_url('/?m=140501'));

        $this->assertTrue(is_month());
        $this->assertStringContainsString('فروردین', \RankMath\Helper::replace_vars('%date%'));
        $this->assertStringContainsString('فروردین', wp_get_document_title());
    }
}
