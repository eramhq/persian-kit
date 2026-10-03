<?php

namespace PersianKit\Service\Import\Sources\PersianWooCommerce;

use PersianKit\Service\Import\AbstractSource;

defined('ABSPATH') || exit;

/**
 * Persian WooCommerce (ووکامرس فارسی): its settings are 'yes'/'no' values
 * in PW_Options.
 */
class PersianWooCommerceSource extends AbstractSource
{
    public const KEY = 'persian-woocommerce';

    /** Its defaults, for options never saved. */
    public const DEFAULTS = [
        'enable_jalali_datepicker'    => 'yes',
        'persian_price'               => 'no',
        'enable_iran_cities'          => 'yes',
        'allowed_states'              => 'all',
        'fix_postcode_persian_number' => 'yes',
        'fix_phone_persian_number'    => 'yes',
        'postcode_validation'         => 'no',
        'phone_validation'            => 'yes',
        'admin_font_family'           => 'iransans',
    ];

    /**
     * Its options that do what Persian Kit does. While any is on, both
     * plugins would convert and check the same things.
     */
    public const OVERLAPPING = [
        'enable_jalali_datepicker',
        'persian_price',
        'enable_iran_cities',
        'fix_postcode_persian_number',
        'fix_phone_persian_number',
        'postcode_validation',
        'phone_validation',
    ];

    public function key(): string
    {
        return self::KEY;
    }

    public function name(): string
    {
        return __('Persian WooCommerce', 'persian-kit');
    }

    public function pluginFile(): string
    {
        return 'persian-woocommerce/woocommerce-persian.php';
    }

    public function hasData(): bool
    {
        return self::arrayOption('PW_Options') !== [];
    }

    /**
     * Null when inactive, or when it is kept only for its payment gateways:
     * every option that overlaps Persian Kit is off.
     */
    public function readyToImport(): ?string
    {
        if (!$this->isActive() || !self::overlapsPersianKit()) {
            return null;
        }

        return __('Deactivate Persian WooCommerce first, or turn off its options that Persian Kit takes over to keep it for its payment gateways.', 'persian-kit');
    }

    /**
     * Whether any of its options that do what Persian Kit does is on.
     */
    public static function overlapsPersianKit(): bool
    {
        return self::overlappingOn() !== [];
    }

    /**
     * Its options that do what Persian Kit does and are on.
     *
     * @return list<string>
     */
    public static function overlappingOn(): array
    {
        $on = [];
        foreach (self::OVERLAPPING as $key) {
            // The shipping plugin turns its city list off while active.
            if ($key === 'enable_iran_cities' && defined('PWS_VERSION')) {
                continue;
            }
            if (self::truthy(self::value($key))) {
                $on[] = $key;
            }
        }

        if (self::value('allowed_states') !== 'all') {
            $on[] = 'allowed_states';
        }
        if (!in_array(self::value('admin_font_family'), ['none', ''], true)) {
            $on[] = 'admin_font_family';
        }

        return $on;
    }

    /**
     * One option as Persian WooCommerce reads it: the saved value, or its default.
     */
    public static function value(string $key): mixed
    {
        $options = self::arrayOption('PW_Options');

        return $options[$key] ?? (self::DEFAULTS[$key] ?? null);
    }

    protected function isLoaded(): bool
    {
        return defined('PW_VERSION') && function_exists('PW');
    }
}
