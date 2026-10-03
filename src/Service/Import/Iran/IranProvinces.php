<?php

namespace PersianKit\Service\Import\Iran;

use PersianKit\Modules\WooCommerce\CityNames;

defined('ABSPATH') || exit;

/**
 * Iran's 31 provinces by WooCommerce code, and the other ways plugins
 * stored them: Persian WooCommerce's and the shipping plugin's two-letter
 * codes (AW for West Azarbaijan), Tapin's numbers, and names spelled in
 * several ways.
 */
final class IranProvinces
{
    /** WooCommerce's codes, with the names in Persian. */
    public const NAMES = [
        'ABZ' => 'البرز',
        'ADL' => 'اردبیل',
        'BHR' => 'بوشهر',
        'CHB' => 'چهارمحال و بختیاری',
        'EAZ' => 'آذربایجان شرقی',
        'ESF' => 'اصفهان',
        'FRS' => 'فارس',
        'GIL' => 'گیلان',
        'GLS' => 'گلستان',
        'GZN' => 'قزوین',
        'HDN' => 'همدان',
        'HRZ' => 'هرمزگان',
        'ILM' => 'ایلام',
        'KBD' => 'کهگیلویه و بویراحمد',
        'KHZ' => 'خوزستان',
        'KRD' => 'کردستان',
        'KRH' => 'کرمانشاه',
        'KRN' => 'کرمان',
        'LRS' => 'لرستان',
        'MKZ' => 'مرکزی',
        'MZN' => 'مازندران',
        'NKH' => 'خراسان شمالی',
        'QHM' => 'قم',
        'RKH' => 'خراسان رضوی',
        'SBN' => 'سیستان و بلوچستان',
        'SKH' => 'خراسان جنوبی',
        'SMN' => 'سمنان',
        'THR' => 'تهران',
        'WAZ' => 'آذربایجان غربی',
        'YZD' => 'یزد',
        'ZJN' => 'زنجان',
    ];

    /** Other spellings seen in plugins' data. */
    public const VARIANTS = [
        'CHB' => ['چهار محال بختیاری', 'چهارمحال بختیاری', 'چهار محال و بختیاری'],
        'KBD' => ['کهگیلوییه و بویراحمد', 'کهکیلویه و بویر احمد', 'کهگیلویه وبویراحمد'],
        'SBN' => ['سیستان بلوچستان'],
        'EAZ' => ['آذربایجان شرقی'],
        'WAZ' => ['آذربایجان غربی'],
    ];

    /**
     * The two-letter codes of older Persian WooCommerce versions, which the
     * shipping plugin still uses as its province slugs.
     */
    public const LEGACY_TWO_LETTER = [
        'AE' => 'EAZ',
        'AL' => 'ABZ',
        'AR' => 'ADL',
        'AW' => 'WAZ',
        'BK' => 'KRH',
        'BU' => 'BHR',
        'CM' => 'CHB',
        'FA' => 'FRS',
        'GI' => 'GIL',
        'GO' => 'GLS',
        'HD' => 'HDN',
        'HG' => 'HRZ',
        'IL' => 'ILM',
        'IS' => 'ESF',
        'KB' => 'KBD',
        'KD' => 'KRD',
        'KE' => 'KRN',
        'KJ' => 'SKH',
        'KS' => 'NKH',
        'KV' => 'RKH',
        'KZ' => 'KHZ',
        'LO' => 'LRS',
        'MK' => 'MKZ',
        'MN' => 'MZN',
        'QM' => 'QHM',
        'QZ' => 'GZN',
        'SB' => 'SBN',
        'SM' => 'SMN',
        'TE' => 'THR',
        'YA' => 'YZD',
        'ZA' => 'ZJN',
    ];

    /** Tapin's province numbers (the shipping plugin's data/tapin.json). */
    public const TAPIN = [
        1  => 'THR',
        2  => 'GIL',
        3  => 'EAZ',
        4  => 'KHZ',
        5  => 'FRS',
        6  => 'ESF',
        7  => 'RKH',
        8  => 'GZN',
        9  => 'SMN',
        10 => 'QHM',
        11 => 'MKZ',
        12 => 'ZJN',
        13 => 'MZN',
        14 => 'GLS',
        15 => 'ADL',
        16 => 'WAZ',
        17 => 'HDN',
        18 => 'KRD',
        19 => 'KRH',
        20 => 'LRS',
        21 => 'BHR',
        22 => 'KRN',
        23 => 'HRZ',
        24 => 'CHB',
        25 => 'YZD',
        26 => 'SBN',
        27 => 'ILM',
        28 => 'KBD',
        29 => 'NKH',
        30 => 'SKH',
        31 => 'ABZ',
    ];

    /** @var array<string, string>|null Folded name => code. */
    private static ?array $byName = null;

    public static function isCode(string $value): bool
    {
        return isset(self::NAMES[$value]);
    }

    public static function fromLegacy(string $value): ?string
    {
        return self::LEGACY_TWO_LETTER[strtoupper(trim($value))] ?? null;
    }

    public static function fromTapin(int $id): ?string
    {
        return self::TAPIN[$id] ?? null;
    }

    /**
     * The code for a province named in any of the usual spellings, with or
     * without "استان" in front.
     */
    public static function fromName(string $name): ?string
    {
        $name = trim(preg_replace('/^\s*استان\s+/u', '', $name) ?? $name);
        if ($name === '') {
            return null;
        }

        if (self::$byName === null) {
            self::$byName = [];
            foreach (self::NAMES as $code => $persian) {
                self::$byName[CityNames::key($persian)] = $code;
            }
            foreach (self::VARIANTS as $code => $names) {
                foreach ($names as $variant) {
                    self::$byName[CityNames::key($variant)] = $code;
                }
            }
        }

        return self::$byName[CityNames::key($name)] ?? null;
    }

    /**
     * A WooCommerce code from any of the forms above that needs no lookup in
     * the shipping plugin's terms: a code, a two-letter code or a name.
     */
    public static function resolve(string $value): ?string
    {
        $value = trim($value);
        $upper = strtoupper($value);

        if (self::isCode($upper)) {
            return $upper;
        }

        return self::fromLegacy($upper) ?? self::fromName($value);
    }

    public static function name(string $code): string
    {
        return self::NAMES[$code] ?? $code;
    }
}
