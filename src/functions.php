<?php

use PersianKit\Modules\DigitConversion\DigitConverter;
use PersianKit\Modules\DateConversion\JalaliFormatter;
use PersianKit\Modules\CharNormalization\CharNormalizer;
use PersianKit\Modules\Utilities\ValidationResult;
use PersianKit\Modules\Utilities\NationalId;
use PersianKit\Modules\Utilities\PhoneNumber;
use PersianKit\Modules\Utilities\CardNumber;
use PersianKit\Modules\Utilities\Iban;
use PersianKit\Modules\Utilities\NumberToWords;
use PersianKit\Modules\Utilities\Slug;

defined('ABSPATH') || exit;

function pk_to_persian_digits(string $text): string
{
    return DigitConverter::toPersian($text);
}

function pk_to_english_digits(string $text): string
{
    return DigitConverter::toEnglish($text);
}

function pk_to_arabic_digits(string $text): string
{
    return DigitConverter::toArabic($text);
}

function pk_date(string $format, int|string $timestamp = '', ?\DateTimeZone $timezone = null): string
{
    return JalaliFormatter::format($format, $timestamp, $timezone);
}

function pk_gregorian_date(string $format, int|string $timestamp = '', ?\DateTimeZone $timezone = null): string
{
    return JalaliFormatter::gregorianFormat($format, $timestamp, $timezone);
}

function pk_normalize_persian(string $text): string
{
    static $normalizer = null;

    if ($normalizer === null) {
        $normalizer = new CharNormalizer();
    }

    return $normalizer->normalize($text);
}

function pk_validate_national_id(string $id): ValidationResult
{
    return NationalId::validate($id);
}

function pk_validate_phone(string $phone): ValidationResult
{
    return PhoneNumber::validate($phone);
}

function pk_validate_card_number(string $card): ValidationResult
{
    return CardNumber::validate($card);
}

function pk_validate_iban(string $iban): ValidationResult
{
    return Iban::validate($iban);
}

function pk_number_to_words(int|float $number): string
{
    return NumberToWords::convert($number);
}

function pk_slug(string $text): string
{
    return Slug::generate($text);
}
