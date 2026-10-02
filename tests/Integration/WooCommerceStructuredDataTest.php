<?php

namespace PersianKit\Tests\Integration;

use PersianKit\Tests\Integration\Support\WordPressIntegrationTestCase;

/**
 * A store priced in thousand toman: WooCommerce's product and order markup
 * gives search engines (and Gmail) rials. Runs when WooCommerce is loaded
 * (see tests/bootstrap.php).
 */
class WooCommerceStructuredDataTest extends WordPressIntegrationTestCase
{
    public function set_up(): void
    {
        parent::set_up();

        if (!class_exists('WooCommerce')) {
            $this->markTestSkipped('WooCommerce is not loaded.');
        }

        update_option('woocommerce_currency', 'IRHT');
    }

    public function test_the_currency_list_and_symbols_have_both_codes(): void
    {
        $currencies = get_woocommerce_currencies();

        $this->assertSame('Iranian thousand toman', $currencies['IRHT']);
        $this->assertSame('Iranian thousand rial', $currencies['IRHR']);
        $this->assertSame('Iranian toman', $currencies['IRT'], 'core\'s are kept');
        $this->assertSame('هزار تومان', get_woocommerce_currency_symbol('IRHT'));
        $this->assertSame('هزار ریال', get_woocommerce_currency_symbol('IRHR'));
        $this->assertSame('هزار تومان', get_woocommerce_currency_symbol());
    }

    public function test_woocommerce_saves_the_new_codes_as_the_store_currency(): void
    {
        $field = $this->currencyField();

        $this->assertArrayHasKey('IRHT', $field['options']);
        $this->assertStringContainsString('doesn\'t convert prices', $field['desc']);
        $this->assertIsString($field['desc_tip']);
    }

    public function test_a_simple_products_price_is_in_rials(): void
    {
        $product = new \WC_Product_Simple();
        $product->set_name('Tea');
        $product->set_regular_price('120');
        $product->save();

        $offer = $this->productMarkup($product)['offers'][0];
        $specification = $offer['priceSpecification'][0];

        $this->assertSame('IRR', $specification['priceCurrency']);
        $this->assertSame('1200000', $specification['price']);
        $this->assertDoesNotMatchRegularExpression('/\bIR[TH]/', (string) wp_json_encode($offer));
    }

    public function test_a_variable_products_price_range_is_in_rials(): void
    {
        $product = new \WC_Product_Variable();
        $product->set_name('Tea');
        $product->save();

        foreach (['12.5', '30'] as $price) {
            $variation = new \WC_Product_Variation();
            $variation->set_parent_id($product->get_id());
            $variation->set_regular_price($price);
            $variation->save();
        }
        \WC_Product_Variable::sync($product->get_id());

        update_option('woocommerce_price_num_decimals', 1);
        $offer = $this->productMarkup(wc_get_product($product->get_id()))['offers'][0];

        $this->assertSame('AggregateOffer', $offer['@type']);
        $this->assertSame(['125000', '300000', 'IRR'], [$offer['lowPrice'], $offer['highPrice'], $offer['priceCurrency']]);
    }

    public function test_an_orders_prices_are_in_rials(): void
    {
        $product = new \WC_Product_Simple();
        $product->set_name('Tea');
        $product->set_regular_price('120');
        $product->save();

        $order = wc_create_order();
        $order->set_currency('IRHT');
        $order->add_product($product, 2);
        $order->calculate_totals(false);
        $order->save();

        $structuredData = new \WC_Structured_Data();
        $structuredData->generate_order_data($order);
        $markup = $structuredData->get_data()[0];

        $this->assertSame(['2400000', 'IRR'], [$markup['price'], $markup['priceCurrency']]);
        $this->assertSame(['2400000', 'IRR'], [$markup['priceSpecification']['price'], $markup['priceSpecification']['priceCurrency']]);
        $this->assertSame(['0', 'IRR'], [$markup['discount'], $markup['discountCurrency']]);
        $this->assertSame(['2400000', 'IRR'], [$markup['acceptedOffer'][0]['price'], $markup['acceptedOffer'][0]['priceCurrency']]);
        $this->assertSame(2, $markup['acceptedOffer'][0]['priceSpecification']['eligibleQuantity']['value'], 'quantities are not prices');
    }

    public function test_a_toman_store_gets_rials_too(): void
    {
        update_option('woocommerce_currency', 'IRT');
        $product = new \WC_Product_Simple();
        $product->set_name('Tea');
        $product->set_regular_price('120000');
        $product->save();

        $specification = $this->productMarkup($product)['offers'][0]['priceSpecification'][0];

        $this->assertSame(['1200000', 'IRR'], [$specification['price'], $specification['priceCurrency']]);
    }

    public function test_the_filter_keeps_the_stores_own_currency(): void
    {
        add_filter('persian_kit_schema_rial_prices', '__return_false');
        $product = new \WC_Product_Simple();
        $product->set_name('Tea');
        $product->set_regular_price('120');
        $product->save();

        $specification = $this->productMarkup($product)['offers'][0]['priceSpecification'][0];
        remove_filter('persian_kit_schema_rial_prices', '__return_false');

        $this->assertSame(['120.00', 'IRHT'], [$specification['price'], $specification['priceCurrency']]);
    }

    /**
     * @return array<string, mixed>
     */
    private function productMarkup(\WC_Product $product): array
    {
        // As on the product's page; WooCommerce's brands read the global post.
        $GLOBALS['post'] = get_post($product->get_id());
        $structuredData = new \WC_Structured_Data();
        $structuredData->generate_product_data($product);

        return $structuredData->get_data()[0];
    }

    /**
     * @return array<string, mixed>
     */
    private function currencyField(): array
    {
        if (!class_exists('WC_Settings_General')) {
            include_once WC_ABSPATH . 'includes/admin/settings/class-wc-settings-page.php';
            include_once WC_ABSPATH . 'includes/admin/settings/class-wc-settings-general.php';
        }

        foreach ((new \WC_Settings_General())->get_settings_for_section('') as $field) {
            if (($field['id'] ?? null) === 'woocommerce_currency') {
                return $field;
            }
        }

        $this->fail('WooCommerce has no currency field.');
    }
}
