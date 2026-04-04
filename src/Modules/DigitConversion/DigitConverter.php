<?php

namespace PersianKit\Modules\DigitConversion;

defined('ABSPATH') || exit;

class DigitConverter
{
    public const ENGLISH_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    public const PERSIAN_DIGITS = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    public const ARABIC_DIGITS  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];

    public static function toPersian(string $text): string
    {
        $text = str_replace(self::ENGLISH_DIGITS, self::PERSIAN_DIGITS, $text);
        $text = str_replace(self::ARABIC_DIGITS, self::PERSIAN_DIGITS, $text);

        return $text;
    }

    public static function toEnglish(string $text): string
    {
        $text = str_replace(self::PERSIAN_DIGITS, self::ENGLISH_DIGITS, $text);
        $text = str_replace(self::ARABIC_DIGITS, self::ENGLISH_DIGITS, $text);

        return $text;
    }

    public static function toArabic(string $text): string
    {
        $text = str_replace(self::ENGLISH_DIGITS, self::ARABIC_DIGITS, $text);
        $text = str_replace(self::PERSIAN_DIGITS, self::ARABIC_DIGITS, $text);

        return $text;
    }

    /**
     * Convert digits in HTML content, skipping tags, scripts, and styles.
     */
    public static function convertContent(string $html): string
    {
        if ($html === '') {
            return $html;
        }

        $pattern = '/((?:<script[\s>][\s\S]*?<\/script>)|(?:<style[\s>][\s\S]*?<\/style>)|(?:<[^>]*>))/si';
        $segments = preg_split($pattern, $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($segments as &$segment) {
            if (!isset($segment[0]) || $segment[0] !== '<') {
                $segment = self::toPersian($segment);
            }
        }

        return implode('', $segments);
    }
}
