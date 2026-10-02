<?php

namespace PersianKit\Tests\Unit\Seo;

use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use PersianKit\Modules\Seo\RankMathPrices;
use PersianKit\Modules\Seo\YoastPrices;
use PHPUnit\Framework\TestCase;

class SeoPricesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_yoast_converts_the_graph_last(): void
    {
        $prices = new YoastPrices();
        $prices->register();

        $this->assertSame(PHP_INT_MAX, has_filter('wpseo_schema_graph', [$prices, 'filterGraph']));
        $this->assertSame(
            [['@type' => 'Product', 'offers' => ['price' => '1200000', 'priceCurrency' => 'IRR']]],
            $prices->filterGraph([['@type' => 'Product', 'offers' => ['price' => '120', 'priceCurrency' => 'IRHT']]])
        );
        $this->assertNull($prices->filterGraph(null));
    }

    public function test_rank_math_hooks_its_schema_and_open_graph_price_tags(): void
    {
        $prices = new RankMathPrices();
        $prices->register();

        $this->assertSame(PHP_INT_MAX, has_filter('rank_math/json_ld', [$prices, 'filterJsonLd']));
        $this->assertNotFalse(has_filter('rank_math/opengraph/facebook/product_price_amount', [$prices, 'filterAmount']));
        $this->assertNotFalse(has_filter('rank_math/opengraph/facebook/product_price_currency', [$prices, 'filterCurrency']));
    }

    public function test_rank_math_schema_is_converted_by_each_entitys_currency(): void
    {
        $data = (new RankMathPrices())->filterJsonLd([
            'WebPage'     => ['@type' => 'WebPage', 'datePublished' => '2026-04-05T13:33:17+03:30'],
            'richSnippet' => ['@type' => 'Product', 'offers' => ['@type' => 'Offer', 'price' => '100000.00', 'priceCurrency' => 'IRHT']],
        ]);

        $this->assertSame(['@type' => 'Offer', 'price' => '1000000000', 'priceCurrency' => 'IRR'], $data['richSnippet']['offers']);
        $this->assertSame('2026-04-05T13:33:17+03:30', $data['WebPage']['datePublished']);
    }

    /**
     * @dataProvider storeCurrencies
     */
    public function test_rank_math_og_price_follows_the_store_currency(string $currency, mixed $amount, mixed $expected, string $tag): void
    {
        Functions\when('get_woocommerce_currency')->justReturn($currency);
        $prices = new RankMathPrices();

        $this->assertSame($expected, $prices->filterAmount($amount));
        $this->assertSame($tag, $prices->filterCurrency($currency));
    }

    /**
     * @return array<string, array{string, mixed, mixed, string}>
     */
    public static function storeCurrencies(): array
    {
        return [
            'thousand toman' => ['IRHT', '120', '1200000', 'IRR'],
            'thousand rial'  => ['IRHR', '12.5', '12500', 'IRR'],
            'toman'          => ['IRT', 120000, '1200000', 'IRR'],
            'rial'           => ['IRR', '1200000', '1200000', 'IRR'],
            'euro'           => ['EUR', '12.50', '12.50', 'EUR'],
            'no price'       => ['IRHT', '', '', 'IRR'],
        ];
    }

    public function test_the_filter_keeps_the_stores_currency_in_open_graph_too(): void
    {
        Functions\when('get_woocommerce_currency')->justReturn('IRHT');
        Filters\expectApplied('persian_kit_schema_rial_prices')->twice()->with(true, 'IRHT')->andReturn(false);
        $prices = new RankMathPrices();

        $this->assertSame('120', $prices->filterAmount('120'));
        $this->assertSame('IRHT', $prices->filterCurrency('IRHT'));
    }
}
