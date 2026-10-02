<?php

namespace PersianKit\Tests\Unit\WooCommerce;

use Brain\Monkey;
use Brain\Monkey\Filters;
use PersianKit\Modules\WooCommerce\SchemaPrices;
use PHPUnit\Framework\TestCase;

class SchemaPricesTest extends TestCase
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

    public function test_register_runs_early_on_product_and_order_markup(): void
    {
        $prices = new SchemaPrices();
        $prices->register();

        $this->assertSame(1, has_filter('woocommerce_structured_data_product', [$prices, 'filter']));
        $this->assertSame(1, has_filter('woocommerce_structured_data_order', [$prices, 'filter']));
    }

    /**
     * @dataProvider iranianPrices
     */
    public function test_iranian_prices_become_whole_rials(string $currency, mixed $price, string $rials): void
    {
        $this->assertSame(
            ['@type' => 'Offer', 'price' => $rials, 'priceCurrency' => 'IRR'],
            SchemaPrices::toRial(['@type' => 'Offer', 'price' => $price, 'priceCurrency' => $currency])
        );
    }

    /**
     * @return array<string, array{string, mixed, string}>
     */
    public static function iranianPrices(): array
    {
        return [
            'toman'                      => ['IRT', '120000', '1200000'],
            'thousand toman'             => ['IRHT', '120', '1200000'],
            'thousand toman, decimal'    => ['IRHT', '12.5', '125000'],
            'thousand rial'              => ['IRHR', '1200', '1200000'],
            'an order total as a float'  => ['IRT', 1234.5, '12345'],
            'an integer'                 => ['IRHT', 3, '30000'],
            'rounded to a whole rial'    => ['IRT', '0.06', '1'],
            'big, without an exponent'   => ['IRHT', '999999.99', '9999999900'],
        ];
    }

    public function test_rials_and_other_currencies_are_left_alone(): void
    {
        $irr = ['price' => '1200000', 'priceCurrency' => 'IRR'];
        $usd = ['price' => '12.50', 'priceCurrency' => 'USD'];

        $this->assertSame($irr, SchemaPrices::toRial($irr));
        $this->assertSame($usd, SchemaPrices::toRial($usd));
        $this->assertSame(['price' => '120'], SchemaPrices::toRial(['price' => '120']), 'no currency');
    }

    public function test_each_node_is_converted_by_its_own_currency(): void
    {
        $markup = SchemaPrices::toRial([
            '@type'  => 'Product',
            'offers' => [
                ['@type' => 'Offer', 'price' => '120', 'priceCurrency' => 'IRHT'],
                ['@type' => 'Offer', 'price' => '9.99', 'priceCurrency' => 'USD'],
            ],
        ]);

        $this->assertSame(['@type' => 'Offer', 'price' => '1200000', 'priceCurrency' => 'IRR'], $markup['offers'][0]);
        $this->assertSame(['@type' => 'Offer', 'price' => '9.99', 'priceCurrency' => 'USD'], $markup['offers'][1]);
    }

    public function test_price_specifications_nested_in_offers(): void
    {
        // WooCommerce 9+ markup for a simple product on sale.
        $markup = SchemaPrices::toRial([
            '@type'  => 'Product',
            'name'   => 'Tea, 120 g',
            'offers' => [[
                '@type'              => 'Offer',
                'priceSpecification' => [
                    [
                        '@type'                 => 'UnitPriceSpecification',
                        'price'                 => '150',
                        'priceCurrency'         => 'IRHT',
                        'valueAddedTaxIncluded' => false,
                        'validThrough'          => '2027-12-31',
                    ],
                    [
                        '@type'         => 'UnitPriceSpecification',
                        'priceType'     => 'https://schema.org/SalePrice',
                        'price'         => '120',
                        'priceCurrency' => 'IRHT',
                    ],
                ],
                'priceValidUntil'    => '2027-12-31',
            ]],
        ]);

        $specification = $markup['offers'][0]['priceSpecification'];
        $this->assertSame(['1500000', 'IRR'], [$specification[0]['price'], $specification[0]['priceCurrency']]);
        $this->assertSame(['1200000', 'IRR'], [$specification[1]['price'], $specification[1]['priceCurrency']]);
        $this->assertSame('2027-12-31', $specification[0]['validThrough'], 'dates are not prices');
        $this->assertFalse($specification[0]['valueAddedTaxIncluded']);
        $this->assertSame('Tea, 120 g', $markup['name']);
    }

    public function test_aggregate_offer_low_and_high_prices(): void
    {
        $this->assertSame([
            '@type'         => 'AggregateOffer',
            'lowPrice'      => '1000000',
            'highPrice'     => '2500000',
            'offerCount'    => 3,
            'priceCurrency' => 'IRR',
        ], SchemaPrices::toRial([
            '@type'         => 'AggregateOffer',
            'lowPrice'      => '100',
            'highPrice'     => '250',
            'offerCount'    => 3,
            'priceCurrency' => 'IRHT',
        ]));
    }

    public function test_min_and_max_prices(): void
    {
        $node = SchemaPrices::toRial(['@type' => 'PriceSpecification', 'minPrice' => '10', 'maxPrice' => '20', 'priceCurrency' => 'IRHR']);

        $this->assertSame(['10000', '20000', 'IRR'], [$node['minPrice'], $node['maxPrice'], $node['priceCurrency']]);
    }

    public function test_monetary_amounts_such_as_shipping_rates(): void
    {
        $this->assertSame(
            ['shippingDetails' => ['shippingRate' => ['@type' => 'MonetaryAmount', 'value' => '500000', 'currency' => 'IRR']]],
            SchemaPrices::toRial(['shippingDetails' => ['shippingRate' => ['@type' => 'MonetaryAmount', 'value' => '50', 'currency' => 'IRHT']]])
        );

        $range = SchemaPrices::toRial(['@type' => 'MonetaryAmount', 'minValue' => 5, 'maxValue' => '7.5', 'currency' => 'IRT']);
        $this->assertSame(['50', '75', 'IRR'], [$range['minValue'], $range['maxValue'], $range['currency']]);
    }

    public function test_an_orders_discount(): void
    {
        $order = SchemaPrices::toRial(['@type' => 'Order', 'discount' => 20.0, 'discountCurrency' => 'IRHT', 'price' => 100.0, 'priceCurrency' => 'IRHT']);

        $this->assertSame(['200000', 'IRR', '1000000', 'IRR'], [$order['discount'], $order['discountCurrency'], $order['price'], $order['priceCurrency']]);
    }

    public function test_prices_that_are_not_numbers_are_kept(): void
    {
        $node = SchemaPrices::toRial(['price' => '', 'lowPrice' => '۱۲۰', 'highPrice' => null, 'maxPrice' => true, 'priceCurrency' => 'IRHT']);

        $this->assertSame(['price' => '', 'lowPrice' => '۱۲۰', 'highPrice' => null, 'maxPrice' => true, 'priceCurrency' => 'IRR'], $node);
    }

    public function test_converting_twice_changes_nothing_more(): void
    {
        $once = SchemaPrices::toRial(['offers' => [['price' => '120', 'priceCurrency' => 'IRHT']]]);

        $this->assertSame($once, SchemaPrices::toRial($once));
        $this->assertSame('1200000', $once['offers'][0]['price']);
    }

    public function test_the_filter_keeps_the_stores_own_currency(): void
    {
        Filters\expectApplied('persian_kit_schema_rial_prices')->once()->with(true, 'IRHT')->andReturn(false);

        $node = ['price' => '120', 'priceCurrency' => 'IRHT'];
        $this->assertSame($node, SchemaPrices::toRial($node));
    }

    public function test_the_hook_callback_passes_on_anything_but_an_array(): void
    {
        $prices = new SchemaPrices();

        $this->assertNull($prices->filter(null));
        $this->assertSame(['price' => '1200000', 'priceCurrency' => 'IRR'], $prices->filter(['price' => '120', 'priceCurrency' => 'IRHT']));
    }
}
