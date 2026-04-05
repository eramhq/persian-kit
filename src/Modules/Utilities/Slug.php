<?php

namespace PersianKit\Modules\Utilities;

use PersianKit\Modules\DigitConversion\DigitConverter;
use PersianKit\Modules\CharNormalization\CharNormalizer;

defined('ABSPATH') || exit;

class Slug
{
    public static function generate(string $text): string
    {
        static $normalizer = null;
        if ($normalizer === null) {
            $normalizer = new CharNormalizer();
        }

        $text = $normalizer->normalize($text);
        $text = DigitConverter::toEnglish($text);
        $text = mb_strtolower($text, 'UTF-8');
        $text = preg_replace('/[\s_]+/u', '-', $text);
        $text = preg_replace('/[^\x{0600}-\x{06FF}\x{200C}a-z0-9\-]/u', '', $text);
        $text = preg_replace('/-+/', '-', $text);

        return trim($text, '-');
    }

    /**
     * WordPress filter callback for sanitize_title.
     */
    public static function sanitizeTitleFilter(string $title, string $rawTitle = '', string $context = ''): string
    {
        $source = $rawTitle !== '' ? $rawTitle : $title;

        return self::generate($source);
    }
}
