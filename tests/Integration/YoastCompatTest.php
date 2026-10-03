<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Tests\Integration\Support\ReadsSeoOutput;
use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * Yoast SEO's output for search engines stays valid with Persian Kit
 * converting every date and digit: Gregorian dates and rials. Runs when
 * Yoast SEO is loaded (see tests/bootstrap.php).
 */
class YoastCompatTest extends WordPressIntegrationTestCase
{
    use ReadsSeoOutput;

    public function set_up(): void
    {
        parent::set_up();

        if (!defined('WPSEO_VERSION')) {
            $this->markTestSkipped('Yoast SEO is not loaded.');
        }

        $this->convertEverything();
    }

    public function test_a_posts_schema_and_open_graph_dates_are_gregorian(): void
    {
        $html = $this->head(get_permalink($this->postOnFarvardin16()));
        $graph = $this->jsonLd($html, 'yoast-schema-graph');

        $dates = $this->valuesOf($graph, ['datePublished', 'dateModified']);
        $this->assertContains('2026-04-05T10:00:00+00:00', $dates);
        array_map([$this, 'assertMachineDate'], $dates);

        // Rank Math, when loaded too, prints its own.
        $this->assertContains('2026-04-05T10:00:00+00:00', $this->meta($html, 'article:published_time'));
        array_map([$this, 'assertMachineDate'], array_merge(
            $this->meta($html, 'article:published_time'),
            $this->meta($html, 'article:modified_time')
        ));
    }

    public function test_a_products_prices_are_in_rials(): void
    {
        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is not loaded.');
        }

        $html = $this->head(get_permalink($this->productInThousandToman()));
        $graph = $this->jsonLd($html, 'yoast-schema-graph');

        $this->assertNotContains('IRHT', $this->valuesOf($graph, ['priceCurrency', 'currency']));
        $this->assertDoesNotMatchRegularExpression('/IRH[TR]/', $html);
    }

    public function test_the_graph_filter_converts_what_yoast_and_its_add_ons_add(): void
    {
        $graph = apply_filters('wpseo_schema_graph', [[
            '@type'  => 'Product',
            'offers' => ['@type' => 'Offer', 'price' => '120', 'priceCurrency' => 'IRHT'],
        ]], null);

        $this->assertSame(['@type' => 'Offer', 'price' => '1200000', 'priceCurrency' => 'IRR'], $graph[0]['offers']);
    }

    public function test_sitemap_lastmod_is_gregorian(): void
    {
        $this->postOnFarvardin16();
        $sitemaps = $GLOBALS['wpseo_sitemaps'];
        $xml = '';

        foreach ($sitemaps->providers as $provider) {
            if ($provider->handles_type('post')) {
                $xml = $sitemaps->renderer->get_sitemap($provider->get_sitemap_links('post', 1000, 1), 'post', 1);
                break;
            }
        }

        $this->assertGreaterThan(0, preg_match_all('#<lastmod>([^<]+)</lastmod>#', $xml, $matches));
        array_map([$this, 'assertMachineDate'], $matches[1]);
    }

    public function test_only_the_tab_title_gets_persian_digits(): void
    {
        $html = $this->head(get_permalink($this->postTitledTop10()));

        $this->assertOnlyTheTabTitleConverts($html, 'yoast-schema-graph', ['headline', 'name']);
    }

    public function test_a_jalali_month_archive_is_named_for_its_jalali_month(): void
    {
        $this->postOnFarvardin16();
        $this->go_to(home_url('/?m=140501'));

        $this->assertTrue(is_month());
        // Yoast's own title; Rank Math, when loaded too, writes the document title.
        $this->assertStringContainsString('فروردین', YoastSEO()->meta->for_current_page()->title);
    }
}
