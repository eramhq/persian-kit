<?php

namespace PersianKit\Modules\WooCommerce;

defined('ABSPATH') || exit;

/**
 * Finds the listed spelling of a city typed another way: قائمشهر is
 * قایم شهر, مشکین شهر is مشگین شهر. The key is the one
 * resources/js/woocommerce-city-select.js computes, so a name the
 * suggestions match exactly is the name saved.
 *
 * Only a whole name matches, never a prefix, so a village named like part
 * of a city is kept as typed.
 */
class CityNames
{
    private const COUNTRY = 'IR';

    /** Letters with more than one spelling, folded to one. */
    private const FOLD = [
        'ي' => 'ی', 'ى' => 'ی', 'ئ' => 'ی',
        'ك' => 'ک', 'گ' => 'ک',
        'ة' => 'ه', 'ۀ' => 'ه', 'ە' => 'ه', 'ھ' => 'ه', 'ہ' => 'ه',
        'أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ٱ' => 'ا',
        'ؤ' => 'و',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    /**
     * Spaces, zero-width and direction marks (ZWNJ included), kashida,
     * tashkeel, hamza, hyphens and dashes, and . ( ) /. Kept in step with
     * the script's list.
     */
    private const STRIP = '/[\t\n\x{000B}\f\r \x{00A0}\x{1680}\x{2000}-\x{200F}\x{2028}-\x{202F}\x{205F}\x{2060}-\x{2069}\x{3000}\x{FEFF}'
        . '\x{0640}\x{064B}-\x{065F}\x{0670}\x{0621}\-\x{2010}-\x{2015}\x{2212}\x{FE58}\x{FE63}\x{FF0D}.()\/]/u';

    /** @var array<string, list<string>> */
    private array $cities;

    /** @var array<string, array<string, string|false>> Per province: key => name, or false when two names share it. */
    private array $index = [];

    /**
     * @param array<string, list<string>> $cities Names by WooCommerce state code.
     */
    public function __construct(array $cities)
    {
        $this->cities = $cities;
    }

    public static function key(string $name): string
    {
        $folded = strtr($name, self::FOLD);
        $key = preg_replace(self::STRIP, '', $folded);

        return is_string($key) ? $key : $folded;
    }

    /**
     * The listed name for a city typed in an Iranian province, or null to
     * save it as typed: another country, a province with no list, no listed
     * name with the same key, or two that share it.
     */
    public function officialName(string $country, string $state, string $city): ?string
    {
        if ($country !== self::COUNTRY || !isset($this->cities[$state])) {
            return null;
        }

        $key = self::key($city);
        if ($key === '') {
            return null;
        }

        $name = $this->indexFor($state)[$key] ?? false;

        return $name === false ? null : $name;
    }

    /**
     * @return array<string, string|false>
     */
    private function indexFor(string $state): array
    {
        if (isset($this->index[$state])) {
            return $this->index[$state];
        }

        $index = [];
        foreach ($this->cities[$state] as $name) {
            $key = self::key($name);
            if ($key === '') {
                continue;
            }

            // The same name listed twice is still one name.
            $index[$key] = isset($index[$key]) && $index[$key] !== $name ? false : $name;
        }

        return $this->index[$state] = $index;
    }
}
