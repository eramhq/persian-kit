<?php

namespace PersianKit\Tests\Unit\WooCommerce;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\WooCommerce\IranianCurrencies;
use PHPUnit\Framework\TestCase;

class IranianCurrenciesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\stubTranslationFunctions();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_register_hooks_the_currency_list_symbols_and_settings(): void
    {
        $currencies = new IranianCurrencies();
        $currencies->register();

        $this->assertNotFalse(has_filter('woocommerce_currencies', [$currencies, 'addCurrencies']));
        $this->assertNotFalse(has_filter('woocommerce_currency_symbols', [$currencies, 'addSymbols']));
        $this->assertNotFalse(has_filter('woocommerce_general_settings', [$currencies, 'addCurrencyNote']));
    }

    public function test_both_codes_join_the_list_after_the_existing_ones(): void
    {
        $currencies = (new IranianCurrencies())->addCurrencies(['IRR' => 'Iranian rial', 'IRT' => 'Iranian toman']);

        $this->assertSame([
            'IRR'  => 'Iranian rial',
            'IRT'  => 'Iranian toman',
            'IRHT' => 'Iranian thousand toman',
            'IRHR' => 'Iranian thousand rial',
        ], $currencies);
    }

    public function test_a_label_and_symbol_another_plugin_gave_the_code_are_kept(): void
    {
        $currencies = new IranianCurrencies();

        $this->assertSame('هزار تومان', $currencies->addCurrencies(['IRHT' => 'هزار تومان'])['IRHT']);
        $this->assertSame('هزار تومان ایران', $currencies->addSymbols(['IRHT' => 'هزار تومان ایران'])['IRHT']);
    }

    public function test_the_symbols_are_persian_words(): void
    {
        $symbols = (new IranianCurrencies())->addSymbols(['IRT' => '&#x062A;&#x0648;&#x0645;&#x0627;&#x0646;']);

        $this->assertSame('هزار تومان', $symbols['IRHT']);
        $this->assertSame('هزار ریال', $symbols['IRHR']);
        $this->assertSame('&#x062A;&#x0648;&#x0645;&#x0627;&#x0646;', $symbols['IRT']);
    }

    public function test_something_other_than_a_list_is_passed_on(): void
    {
        $currencies = new IranianCurrencies();

        $this->assertNull($currencies->addCurrencies(null));
        $this->assertSame('', $currencies->addSymbols(''));
        $this->assertFalse($currencies->addCurrencyNote(false));
    }

    public function test_rial_factor_for_each_iranian_currency(): void
    {
        $this->assertSame(1, IranianCurrencies::rialFactor('IRR'));
        $this->assertSame(10, IranianCurrencies::rialFactor('IRT'));
        $this->assertSame(1000, IranianCurrencies::rialFactor('IRHR'));
        $this->assertSame(10000, IranianCurrencies::rialFactor('IRHT'));
        $this->assertNull(IranianCurrencies::rialFactor('USD'));
        $this->assertNull(IranianCurrencies::rialFactor('irt'));
        $this->assertNull(IranianCurrencies::rialFactor(''));
    }

    public function test_the_note_shows_under_the_currency_field_and_its_tooltip_keeps_woocommerces_text(): void
    {
        $settings = (new IranianCurrencies())->addCurrencyNote([
            ['id' => 'pricing_options', 'type' => 'title', 'desc' => 'The following options affect how prices are displayed.'],
            ['id' => 'woocommerce_currency', 'type' => 'select', 'desc' => 'This controls what currency prices are listed at.', 'desc_tip' => true],
            ['id' => 'woocommerce_price_num_decimals', 'type' => 'number', 'desc' => 'Number of decimal points.', 'desc_tip' => true],
        ]);

        $this->assertSame('This controls what currency prices are listed at.', $settings[1]['desc_tip']);
        $this->assertStringStartsWith('Changing the currency doesn\'t convert prices, shipping costs or coupons.', $settings[1]['desc']);
        $this->assertStringContainsString('payment gateway', $settings[1]['desc']);
        $this->assertStringContainsString('number of decimals', $settings[1]['desc']);

        $this->assertSame('The following options affect how prices are displayed.', $settings[0]['desc'], 'other fields are untouched');
        $this->assertSame('Number of decimal points.', $settings[2]['desc']);
        $this->assertTrue($settings[2]['desc_tip']);
    }

    public function test_the_note_follows_a_description_shown_under_the_field(): void
    {
        $settings = (new IranianCurrencies())->addCurrencyNote([
            ['id' => 'woocommerce_currency', 'type' => 'select', 'desc' => 'Pick one.'],
        ]);

        $this->assertStringStartsWith('Pick one. Changing the currency', $settings[0]['desc']);
        $this->assertArrayNotHasKey('desc_tip', $settings[0]);
    }
}
