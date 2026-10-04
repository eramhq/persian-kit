<?php

namespace PersianKit\Tests\Unit\Forms;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PersianKit\Modules\Forms\GravityFormsCurrencies;
use PHPUnit\Framework\TestCase;

class GravityFormsCurrenciesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\when('__')->returnArg();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_toman_and_rial_have_no_decimals_and_the_word_after_the_amount(): void
    {
        $currencies = new GravityFormsCurrencies();
        $currencies->register();
        $this->assertNotFalse(has_filter('gform_currencies', [$currencies, 'addCurrencies']));

        $list = $currencies->addCurrencies(['USD' => ['name' => 'U.S. Dollar']]);

        $this->assertSame(['USD', 'IRT', 'IRR'], array_keys($list));
        $this->assertSame([
            'name'               => 'Iranian toman',
            'symbol_left'        => '',
            'symbol_right'       => 'تومان',
            'symbol_padding'     => ' ',
            'thousand_separator' => ',',
            'decimal_separator'  => '.',
            'decimals'           => 0,
            'code'               => 'IRT',
        ], $list['IRT']);
        $this->assertSame('ریال', $list['IRR']['symbol_right']);
    }

    public function test_currencies_another_plugin_added_are_kept(): void
    {
        $theirs = ['IRR' => ['name' => 'ریال ایران', 'decimals' => 0]];

        $this->assertSame($theirs['IRR'], (new GravityFormsCurrencies())->addCurrencies($theirs)['IRR']);
    }
}
