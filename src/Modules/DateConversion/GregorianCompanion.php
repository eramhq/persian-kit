<?php

namespace PersianKit\Modules\DateConversion;

use PersianKit\Service\Language\ContentLanguage;

defined('ABSPATH') || exit;

/**
 * Adds the Gregorian date next to a Jalali post or comment date on the
 * site, such as "۱۰ مهر ۱۴۰۵ (2026-10-02)". The result is plain text, as it
 * also lands in attributes.
 */
final class GregorianCompanion
{
    public const STYLES = ['numeric', 'named'];

    public const ORDERS = ['jalali_first', 'gregorian_first'];

    public const SEPARATORS = ['parentheses', 'slash', 'dash'];

    private const DAY_TOKENS = ['d', 'j'];

    private const MONTH_TOKENS = ['F', 'M', 'm', 'n'];

    private const YEAR_TOKENS = ['Y', 'y', 'o'];

    /**
     * Themes ask for these to fill datetime attributes and data; a site
     * that shows dates this way has it as its date format.
     */
    private const DATA_FORMATS = ['Y-m-d', 'Ymd'];

    // Left-to-right isolate and its closing mark, both invisible.
    private const LRI = "\u{2066}";

    private const PDI = "\u{2069}";

    private bool $enabled;

    private string $style;

    private string $order;

    private string $separator;

    public function __construct(bool $enabled, string $style = 'numeric', string $order = 'jalali_first', string $separator = 'parentheses')
    {
        $this->enabled = $enabled;
        $this->style = in_array($style, self::STYLES, true) ? $style : self::STYLES[0];
        $this->order = in_array($order, self::ORDERS, true) ? $order : self::ORDERS[0];
        $this->separator = in_array($separator, self::SEPARATORS, true) ? $separator : self::SEPARATORS[0];
    }

    /**
     * Appends (or puts first) the Gregorian date of $dateTime when $format
     * shows a full date. Otherwise returns $jalali as it is.
     */
    public function append(string $jalali, string $format, \DateTimeInterface $dateTime): string
    {
        if (!$this->appliesTo($format) || !apply_filters('persian_kit_gregorian_date', true, $format, $dateTime)) {
            return $jalali;
        }

        $gregorianFormat = $this->style === 'named' ? 'j F Y' : 'Y-m-d';
        $gregorian = (string) apply_filters(
            'persian_kit_gregorian_date_display',
            self::gregorian($gregorianFormat, $dateTime),
            $gregorianFormat,
            $dateTime->getTimestamp(),
            $dateTime->getTimezone()
        );

        if ($gregorian === '') {
            return $jalali;
        }

        if ($this->style === 'numeric') {
            $gregorian = self::isolate($gregorian);
        }

        return $this->order === 'gregorian_first'
            ? self::join($gregorian, $jalali, $this->separator)
            : self::join($jalali, $gregorian, $this->separator);
    }

    /**
     * Whether $format has an unescaped day, month and year token.
     */
    public static function isFullDate(string $format): bool
    {
        $found = ['day' => false, 'month' => false, 'year' => false];
        $length = strlen($format);

        for ($i = 0; $i < $length; $i++) {
            $token = $format[$i];

            if ($token === '\\') {
                $i++;
                continue;
            }

            if (in_array($token, self::DAY_TOKENS, true)) {
                $found['day'] = true;
            } elseif (in_array($token, self::MONTH_TOKENS, true)) {
                $found['month'] = true;
            } elseif (in_array($token, self::YEAR_TOKENS, true)) {
                $found['year'] = true;
            }
        }

        return !in_array(false, $found, true);
    }

    /**
     * The Gregorian date in "Y-m-d", or "j F Y" with the month names of
     * this request's set (CalendarNames).
     */
    public static function gregorian(string $format, \DateTimeInterface $dateTime): string
    {
        if ($format !== 'j F Y') {
            return $dateTime->format($format);
        }

        $month = CalendarNames::current()['gregorian_months'][(int) $dateTime->format('n')];

        return $dateTime->format('j') . ' ' . $month . ' ' . $dateTime->format('Y');
    }

    /**
     * After a Persian word, digits count as Arabic numbers, and the hyphens
     * between them no longer hold them together: "2026-10-02" would read
     * "02-10-2026" on the page. Isolated, it reads left to right.
     */
    public static function isolate(string $date): string
    {
        return self::LRI . $date . self::PDI;
    }

    public static function join(string $first, string $second, string $separator): string
    {
        return match ($separator) {
            'slash' => $first . ' / ' . $second,
            'dash'  => $first . ' – ' . $second,
            default => $first . ' (' . $second . ')',
        };
    }

    private function appliesTo(string $format): bool
    {
        if (!$this->enabled || DateDisplayGuard::isMachineFormat($format) || !self::isFullDate($format)) {
            return false;
        }

        if (in_array($format, self::DATA_FORMATS, true) && $format !== get_option('date_format')) {
            return false;
        }

        // The block editor previews dates over REST; admin screens keep one date.
        return !ContentLanguage::isAdminRequest() && !wp_is_serving_rest_request();
    }
}
