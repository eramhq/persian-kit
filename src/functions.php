<?php

use PersianKit\Modules\DigitConversion\DigitConverter;
use PersianKit\Modules\DateConversion\JalaliFormatter;

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
