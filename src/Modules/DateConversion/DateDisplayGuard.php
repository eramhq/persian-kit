<?php

namespace PersianKit\Modules\DateConversion;

use PersianKit\Service\Language\ContentLanguage;

defined('ABSPATH') || exit;

/**
 * Decides when a date filter must leave WordPress's Gregorian output alone:
 * in feeds, for formats that machines parse rather than people read, and on
 * multilingual sites in pages and emails that are not in Persian.
 */
final class DateDisplayGuard
{
    /** @var list<string>|null */
    private static ?array $machineFormats = null;

    public static function shouldBypass(string $format): bool
    {
        return self::isMachineFormat($format) || self::isFeed() || !ContentLanguage::displaysPersian();
    }

    public static function isMachineFormat(string $format): bool
    {
        self::$machineFormats ??= array_values(array_unique(array_filter([
            'U',
            'G',
            'c',
            'r',
            'Y-m-d H:i:s',
            'Y-m-d\TH:i:s\Z',
            \DATE_ATOM,
            \DATE_COOKIE,
            \DATE_ISO8601,
            defined('DATE_ISO8601_EXPANDED') ? \DATE_ISO8601_EXPANDED : null,
            \DATE_RFC822,
            \DATE_RFC850,
            \DATE_RFC1036,
            \DATE_RFC1123,
            'D, d M Y H:i:s \\G\\M\\T',
            \DATE_RFC2822,
            \DATE_RFC3339,
            \DATE_RFC3339_EXTENDED,
            \DATE_W3C,
        ], static fn (?string $value): bool => $value !== null)));

        return in_array($format, self::$machineFormats, true);
    }

    private static function isFeed(): bool
    {
        // is_feed() warns when called before the main query object exists.
        return isset($GLOBALS['wp_query']) && function_exists('is_feed') && is_feed();
    }
}
