<?php

namespace PersianKit\Modules\CharNormalization;

use PersianKit\Modules\DigitConversion\DigitConverter;

defined('ABSPATH') || exit;

class CharNormalizer
{
    /** @var array<string> */
    private array $search;

    /** @var array<string> */
    private array $replace;

    public function __construct(bool $tehMarbuta = false)
    {
        $this->search = array_merge(
            ["\u{064A}", "\u{0643}"], // Arabic Yeh, Arabic Kaf
            DigitConverter::ARABIC_DIGITS
        );

        $this->replace = array_merge(
            ["\u{06CC}", "\u{06A9}"], // Persian Yeh, Persian Kaf
            DigitConverter::PERSIAN_DIGITS
        );

        if ($tehMarbuta) {
            $this->search[]  = "\u{0629}"; // Arabic Teh Marbuta
            $this->replace[] = "\u{0647}"; // Persian Heh
        }
    }

    public function normalize(string $text): string
    {
        return str_replace($this->search, $this->replace, $text);
    }

    public function normalizeContent(string $html): string
    {
        if ($html === '') {
            return $html;
        }

        $pattern = '/((?:<script[\s>][\s\S]*?<\/script>)|(?:<style[\s>][\s\S]*?<\/style>)|(?:<!--[\s\S]*?-->)|(?:<[^>]*>))/si';
        $segments = preg_split($pattern, $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($segments as &$segment) {
            if (!isset($segment[0]) || $segment[0] !== '<') {
                $segment = $this->normalize($segment);
            }
        }

        return implode('', $segments);
    }

    public function normalizeForSearch(string $text): string
    {
        $text = $this->normalize($text);

        return DigitConverter::toEnglish($text);
    }
}
