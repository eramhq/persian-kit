<?php

namespace PersianKit\Modules\Forms;

use PersianKit\Modules\WooCommerce\IranianCurrencies;

defined('ABSPATH') || exit;

/**
 * Toman and rial in Gravity Forms' currency setting, with the codes Iranian
 * payment gateways and WooCommerce use (IRT and IRR): no decimals, a comma
 * between thousands, and the Persian word after the amount, as in
 * 250,000 تومان.
 *
 * They stay while the integration is off, so forms priced in them keep
 * their prices. Currencies another plugin already added under these codes
 * are kept.
 */
class GravityFormsCurrencies
{
    public function register(): void
    {
        add_filter('gform_currencies', [$this, 'addCurrencies']);
    }

    /**
     * @param mixed $currencies
     * @return mixed
     */
    public function addCurrencies($currencies)
    {
        if (!is_array($currencies)) {
            return $currencies;
        }

        return $currencies + [
            IranianCurrencies::TOMAN => self::currency(IranianCurrencies::TOMAN, __('Iranian toman', 'persian-kit'), 'تومان'),
            IranianCurrencies::RIAL  => self::currency(IranianCurrencies::RIAL, __('Iranian rial', 'persian-kit'), 'ریال'),
        ];
    }

    /**
     * @return array{name: string, symbol_left: string, symbol_right: string, symbol_padding: string, thousand_separator: string, decimal_separator: string, decimals: int, code: string}
     */
    private static function currency(string $code, string $name, string $symbol): array
    {
        return [
            'name'               => $name,
            'symbol_left'        => '',
            'symbol_right'       => $symbol,
            'symbol_padding'     => ' ',
            'thousand_separator' => ',',
            'decimal_separator'  => '.',
            'decimals'           => 0,
            'code'               => $code,
        ];
    }
}
