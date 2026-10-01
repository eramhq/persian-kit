<?php

use PersianKit\Dependencies\Eram\Abzar\Digits\DigitConverter;
use PersianKit\Dependencies\Eram\Abzar\Exception\EnvironmentException;
use PersianKit\Dependencies\Eram\Abzar\Format\NumberFormatter;
use PersianKit\Dependencies\Eram\Abzar\Format\NumberToWords;
use PersianKit\Dependencies\Eram\Abzar\Format\OrdinalNumber;
use PersianKit\Dependencies\Eram\Abzar\Format\TimeAgo;
use PersianKit\Dependencies\Eram\Abzar\Format\WordsToNumber;
use PersianKit\Dependencies\Eram\Abzar\Money\Currency;
use PersianKit\Dependencies\Eram\Abzar\Money\Unit;
use PersianKit\Dependencies\Eram\Abzar\Text\CharNormalizer;
use PersianKit\Dependencies\Eram\Abzar\Text\HalfSpaceFixer;
use PersianKit\Dependencies\Eram\Abzar\Text\KeyboardFixer;
use PersianKit\Dependencies\Eram\Abzar\Text\PersianCollator;
use PersianKit\Dependencies\Eram\Abzar\Text\Script;
use PersianKit\Dependencies\Eram\Abzar\Text\Slug;
use PersianKit\Dependencies\Eram\Abzar\Validation\BillId;
use PersianKit\Dependencies\Eram\Abzar\Validation\CardNumber;
use PersianKit\Dependencies\Eram\Abzar\Validation\Iban;
use PersianKit\Dependencies\Eram\Abzar\Validation\LegalId;
use PersianKit\Dependencies\Eram\Abzar\Validation\NationalId;
use PersianKit\Dependencies\Eram\Abzar\Validation\PhoneNumber;
use PersianKit\Dependencies\Eram\Abzar\Validation\PlateNumber;
use PersianKit\Dependencies\Eram\Abzar\Validation\PostalCode;
use PersianKit\Dependencies\Eram\Abzar\Validation\ValidationResult;
use PersianKit\Modules\DateConversion\JalaliFormatter;

defined('ABSPATH') || exit;

function persian_kit_to_persian_digits(string $text): string
{
    return DigitConverter::toPersian($text);
}

function persian_kit_to_english_digits(string $text): string
{
    return DigitConverter::toEnglish($text);
}

function persian_kit_to_arabic_digits(string $text): string
{
    return DigitConverter::toArabic($text);
}

function persian_kit_normalize_persian(string $text): string
{
    static $normalizer = null;

    if ($normalizer === null) {
        $normalizer = new CharNormalizer();
    }

    return $normalizer->normalize($text);
}

function persian_kit_slug(string $text): string
{
    return Slug::generate($text);
}

function persian_kit_is_persian(string $text, bool $complex = false): bool
{
    return Script::isPersian($text, $complex);
}

function persian_kit_has_persian(string $text, bool $complex = false): bool
{
    return Script::hasPersian($text, $complex);
}

function persian_kit_is_arabic(string $text): bool
{
    return Script::isArabic($text);
}

function persian_kit_has_arabic(string $text): bool
{
    return Script::hasArabic($text);
}

function persian_kit_half_space_fix(string $text): string
{
    return HalfSpaceFixer::fix($text);
}

function persian_kit_keyboard_fix(string $text): string
{
    return KeyboardFixer::detect($text)
        ? KeyboardFixer::enToFa($text)
        : KeyboardFixer::faToEn($text);
}

/**
 * Persian-aware sort. Returns a new sorted array (does NOT mutate by reference).
 * Falls back to native sort when ext-intl is missing; the collation-correct path
 * requires ext-intl.
 *
 * @template T
 * @param array<int|string, T> $items
 * @param (callable(T): string)|null $key Optional extractor when items are not strings.
 * @return array<int|string, T>
 */
function persian_kit_persian_sort(array $items, ?callable $key = null): array
{
    try {
        $collator = new PersianCollator();
        return $key === null
            ? $collator->sort($items)
            : $collator->sortBy($items, $key);
    } catch (EnvironmentException) {
        if ($key === null) {
            sort($items);
            return $items;
        }
        usort($items, static fn ($a, $b) => strcmp($key($a), $key($b)));
        return $items;
    }
}

function persian_kit_date(string $format, int|string $timestamp = '', ?\DateTimeZone $timezone = null): string
{
    return JalaliFormatter::format($format, $timestamp, $timezone);
}

function persian_kit_gregorian_date(string $format, int|string $timestamp = '', ?\DateTimeZone $timezone = null): string
{
    return JalaliFormatter::gregorianFormat($format, $timestamp, $timezone);
}

function persian_kit_number_format(int|float|string $number, string $separator = ','): string
{
    return NumberFormatter::withSeparators($number, $separator);
}

function persian_kit_number_to_words(int|float $number): string
{
    return NumberToWords::convert($number);
}

function persian_kit_words_to_number(string $words): int|float|null
{
    return WordsToNumber::parse($words);
}

function persian_kit_ordinal_word(int $n): string
{
    return OrdinalNumber::toWord($n);
}

/**
 * @param bool|string $digits true for Persian digits, false for English. The legacy
 *                            strings 'persian' / 'english' are still accepted.
 */
function persian_kit_ordinal_short(int $n, bool|string $digits = true): string
{
    if (is_string($digits)) {
        $digits = strtolower($digits) !== 'english';
    }

    return OrdinalNumber::toShort($n, $digits);
}

function persian_kit_time_ago(int|string|\DateTimeInterface $timestamp, ?int $now = null, bool $persianDigits = true): string
{
    return TimeAgo::format($timestamp, $now, $persianDigits);
}

function persian_kit_currency_format(
    int|float|string $amount,
    string $unit = 'toman',
    bool $persianDigits = true,
    bool $withUnit = true,
): string {
    return Currency::format($amount, persian_kit_currency_unit($unit), $persianDigits, $withUnit);
}

function persian_kit_currency_convert(int|float $amount, string $from, string $to): int|float
{
    return Currency::convert($amount, persian_kit_currency_unit($from), persian_kit_currency_unit($to));
}

/**
 * @internal
 */
function persian_kit_currency_unit(string $unit): Unit
{
    return match (strtolower($unit)) {
        'toman' => Unit::TOMAN,
        'rial'  => Unit::RIAL,
        default => throw new \InvalidArgumentException(
            sprintf('Unknown currency unit "%s". Use "toman" or "rial".', esc_html($unit))
        ),
    };
}

function persian_kit_validate_national_id(string $id): ValidationResult
{
    return NationalId::validate($id);
}

function persian_kit_validate_phone(string $phone): ValidationResult
{
    return PhoneNumber::validate($phone);
}

function persian_kit_validate_card_number(string $card): ValidationResult
{
    return CardNumber::validate($card);
}

function persian_kit_validate_iban(string $iban): ValidationResult
{
    return Iban::validate($iban);
}

function persian_kit_validate_legal_id(string $id): ValidationResult
{
    return LegalId::validate($id);
}

function persian_kit_validate_postal_code(string $code): ValidationResult
{
    return PostalCode::validate($code);
}

function persian_kit_validate_plate_number(string $plate): ValidationResult
{
    return PlateNumber::validate($plate);
}

function persian_kit_validate_bill_id(string $billId, ?string $paymentId = null): ValidationResult
{
    return $paymentId === null
        ? BillId::validate($billId)
        : BillId::validatePair($billId, $paymentId);
}
