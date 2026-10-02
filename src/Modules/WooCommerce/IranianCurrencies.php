<?php

namespace PersianKit\Modules\WooCommerce;

defined('ABSPATH') || exit;

/**
 * Thousand toman and thousand rial in WooCommerce's currency list, with the
 * codes Persian WooCommerce and Iran's payment gateways already use, so the
 * gateways convert the total to rials as they do for that plugin.
 *
 * A store priced in one of them keeps its label and symbol while the
 * module is off, so this registers in both states.
 */
class IranianCurrencies
{
    public const RIAL = 'IRR';
    public const TOMAN = 'IRT';
    public const THOUSAND_RIAL = 'IRHR';
    public const THOUSAND_TOMAN = 'IRHT';

    /** Rials in one unit of each Iranian currency. */
    private const RIAL_FACTORS = [
        self::RIAL           => 1,
        self::TOMAN          => 10,
        self::THOUSAND_RIAL  => 1000,
        self::THOUSAND_TOMAN => 10000,
    ];

    public function register(): void
    {
        add_filter('woocommerce_currencies', [$this, 'addCurrencies']);
        add_filter('woocommerce_currency_symbols', [$this, 'addSymbols']);
        add_filter('woocommerce_general_settings', [$this, 'addCurrencyNote']);
    }

    /**
     * How many rials one unit of $code is, or null for a currency that isn't
     * Iranian.
     */
    public static function rialFactor(string $code): ?int
    {
        return self::RIAL_FACTORS[$code] ?? null;
    }

    /**
     * Labels another plugin (Persian WooCommerce) already gave the codes are kept.
     */
    public function addCurrencies(mixed $currencies): mixed
    {
        if (!is_array($currencies)) {
            return $currencies;
        }

        return $currencies + [
            self::THOUSAND_TOMAN => __('Iranian thousand toman', 'persian-kit'),
            self::THOUSAND_RIAL  => __('Iranian thousand rial', 'persian-kit'),
        ];
    }

    /**
     * Persian words in every language, like WooCommerce's own toman symbol.
     */
    public function addSymbols(mixed $symbols): mixed
    {
        if (!is_array($symbols)) {
            return $symbols;
        }

        return $symbols + [
            self::THOUSAND_TOMAN => 'هزار تومان',
            self::THOUSAND_RIAL  => 'هزار ریال',
        ];
    }

    /**
     * A note under WooCommerce › Settings › General › Currency: switching
     * the currency doesn't convert what the store has saved. WooCommerce
     * shows the field's own description as a tooltip, so the note becomes
     * the description and the tooltip keeps WooCommerce's.
     */
    public function addCurrencyNote(mixed $settings): mixed
    {
        if (!is_array($settings)) {
            return $settings;
        }

        $note = implode(' ', [
            __('Changing the currency doesn\'t convert prices, shipping costs or coupons.', 'persian-kit'),
            __('Before you pick thousand toman or thousand rial, check that your payment gateway supports it.', 'persian-kit'),
            __('For prices like 12.5, set the number of decimals below.', 'persian-kit'),
        ]);

        foreach ($settings as $index => $field) {
            if (!is_array($field) || ($field['id'] ?? null) !== 'woocommerce_currency') {
                continue;
            }

            $description = is_string($field['desc'] ?? null) ? $field['desc'] : '';

            if (($field['desc_tip'] ?? false) === true) {
                $field['desc_tip'] = $description;
                $field['desc'] = $note;
            } else {
                $field['desc'] = trim($description . ' ' . $note);
            }

            $settings[$index] = $field;
        }

        return $settings;
    }
}
