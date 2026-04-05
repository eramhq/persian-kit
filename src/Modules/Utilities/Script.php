<?php

namespace PersianKit\Modules\Utilities;

defined('ABSPATH') || exit;

class Script
{
    // Core Persian letters + digits + short vowels + alef variants + ZWNJ
    private const PERSIAN_BASIC = 'آابپتثجچحخدذرزژسشصضطظعغفقکگلمنوهیئؤ'
        . '۰۱۲۳۴۵۶۷۸۹'
        . "\xD9\x8E\xD9\x8F\xD9\x90"  // fatHa, damma, kasra (َُِ)
        . "\xD8\xA7\xD9\x8B"            // alef + tanwin (اً)
        . "\xE2\x80\x8C";               // ZWNJ

    // Extended: diacritics + Arabic overlap chars + tatweel
    private const PERSIAN_COMPLEX_EXTRA = "\xD9\x8B\xD9\x8C\xD9\x8D\xD9\x91\xD9\x92\xD9\xB0\xD9\x94" // tanwin, shadda, sukun, superscript alef, hamza above
        . 'كةأإيئؤ'   // Arabic overlap characters
        . "\xD9\x80"; // tatweel (kashida)

    // Arabic-exclusive contextual forms
    private const ARABIC_EXCLUSIVE = 'يكة';

    public static function isPersian(string $text, bool $complex = false): bool
    {
        $stripped = self::stripNonLetters($text);

        if ($stripped === null) {
            return false;
        }

        $allowedChars = self::PERSIAN_BASIC;
        if ($complex) {
            $allowedChars .= self::PERSIAN_COMPLEX_EXTRA;
        }

        return self::allCharsIn($stripped, $allowedChars);
    }

    public static function hasPersian(string $text, bool $complex = false): bool
    {
        if ($text === '') {
            return false;
        }

        $chars = self::PERSIAN_BASIC;
        if ($complex) {
            $chars .= self::PERSIAN_COMPLEX_EXTRA;
        }

        return self::anyCharIn($text, $chars);
    }

    public static function isArabic(string $text): bool
    {
        $stripped = self::stripNonLetters($text);

        if ($stripped === null) {
            return false;
        }

        if (!preg_match('/^[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]+$/u', $stripped)) {
            return false;
        }

        return self::anyCharIn($stripped, self::ARABIC_EXCLUSIVE);
    }

    public static function hasArabic(string $text): bool
    {
        if ($text === '') {
            return false;
        }

        return self::anyCharIn($text, self::ARABIC_EXCLUSIVE);
    }

    private static function stripNonLetters(string $text): ?string
    {
        if ($text === '') {
            return null;
        }

        $stripped = preg_replace('/[\s\p{P}\p{S}]+/u', '', $text);

        return ($stripped === '' || $stripped === null) ? null : $stripped;
    }

    private static function allCharsIn(string $text, string $allowed): bool
    {
        $pattern = '/^[' . preg_quote($allowed, '/') . ']+$/u';

        return (bool) preg_match($pattern, $text);
    }

    private static function anyCharIn(string $text, string $chars): bool
    {
        $pattern = '/[' . preg_quote($chars, '/') . ']/u';

        return (bool) preg_match($pattern, $text);
    }
}
