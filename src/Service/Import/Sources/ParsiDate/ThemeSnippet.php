<?php

namespace PersianKit\Service\Import\Sources\ParsiDate;

defined('ABSPATH') || exit;

/**
 * Code a site owner copies into their theme's functions.php so calls to
 * Parsi Date's functions keep working with Persian Kit's. Each function is
 * defined only when nothing else defines it, and only while Persian Kit
 * is active. The theme defines these names, not Persian Kit.
 */
final class ThemeSnippet
{
    /** Each function's code, by name. */
    private const FUNCTIONS = [
        'parsidate' => <<<'PHP'
    // A Jalali date. $datetime is a timestamp or a date in site time.
    function parsidate($format, $datetime = 'now', $lang = 'per')
    {
        $timestamp = is_numeric($datetime)
            ? (int) $datetime
            : (new DateTimeImmutable((string) $datetime === '' ? 'now' : (string) $datetime, wp_timezone()))->getTimestamp();
        $date = persian_kit_date((string) $format, $timestamp);

        return $lang === 'eng' || $lang === false ? persian_kit_to_english_digits($date) : persian_kit_to_persian_digits($date);
    }
PHP,
        'gregdate' => <<<'PHP'
    // A Jalali date as Gregorian, or false when it is not a date.
    function gregdate($format, $datetime)
    {
        return persian_kit_jalali_to_gregorian((string) $datetime, (string) $format) ?? false;
    }
PHP,
        'per_number' => <<<'PHP'
    function per_number($number)
    {
        return persian_kit_to_persian_digits((string) $number);
    }
PHP,
        'fix_number' => <<<'PHP'
    function fix_number($content)
    {
        return persian_kit_to_persian_digits((string) $content);
    }
PHP,
        'eng_number' => <<<'PHP'
    function eng_number($number)
    {
        return persian_kit_to_english_digits((string) $number);
    }
PHP,
        'wp_get_parchives' => <<<'PHP'
    // WordPress's archive list, which Persian Kit shows in Jalali.
    function wp_get_parchives($args = '')
    {
        return wp_get_archives($args);
    }
PHP,
        'wpp_is_active' => <<<'PHP'
    // Parsi Date's settings are gone; nothing of it is on.
    function wpp_is_active($option_name)
    {
        return false;
    }
PHP,
        'disable_wpp' => <<<'PHP'
    function disable_wpp()
    {
        return false;
    }
PHP,
        'wpp_date_is' => <<<'PHP'
    function wpp_date_is($date_string, $format = 'Y-m-d\TH:i:sP')
    {
        return (array) date_parse_from_format((string) $format, (string) $date_string);
    }
PHP,
    ];

    /**
     * @param list<string> $functions The functions the theme calls.
     */
    public static function build(array $functions): string
    {
        $functions = array_values(array_intersect(array_keys(self::FUNCTIONS), array_map('strtolower', $functions)));
        if ($functions === []) {
            return '';
        }

        $code = "// Parsi Date's functions, with Persian Kit's. From Persian Kit > Tools.\nif (function_exists('persian_kit_date')) {\n";
        foreach ($functions as $function) {
            $code .= "    if (!function_exists('{$function}')) {\n"
                . preg_replace('/^/m', '    ', self::FUNCTIONS[$function])
                . "\n    }\n";
        }

        return $code . "}\n";
    }
}
