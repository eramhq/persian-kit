<?php

namespace PersianKit\Modules\DateConversion;

use PersianKit\Dependencies\Morilog\Jalali\Jalalian;

defined('ABSPATH') || exit;

class JalaliFormatter
{
    /**
     * Format a timestamp as a Jalali (Shamsi) date string.
     *
     * Returns dates with English digits — digit conversion is a separate concern.
     */
    public static function format(string $format, int|string $timestamp = '', ?\DateTimeZone $timezone = null): string
    {
        $dateTime  = self::resolveDateTime($timestamp, $timezone);
        $jalalian  = Jalalian::fromDateTime($dateTime);
        $result    = $jalalian->format($format);

        return apply_filters('persian_kit_date_display', $result, $format, (int) $dateTime->format('U'), $dateTime->getTimezone());
    }

    /**
     * Format a timestamp as a Gregorian date string.
     *
     * Escape hatch when tier 2 (wp_date hook) is active.
     */
    public static function gregorianFormat(string $format, int|string $timestamp = '', ?\DateTimeZone $timezone = null): string
    {
        return self::resolveDateTime($timestamp, $timezone)->format($format);
    }

    private static function resolveDateTime(int|string $timestamp, ?\DateTimeZone $timezone): \DateTime
    {
        $ts = self::resolveTimestamp($timestamp);
        $tz = $timezone ?? wp_timezone();

        $dateTime = new \DateTime('@' . $ts);
        $dateTime->setTimezone($tz);

        return $dateTime;
    }

    private static function resolveTimestamp(int|string $timestamp): int
    {
        if ($timestamp === '' || $timestamp === 0) {
            return time();
        }

        if (is_string($timestamp)) {
            return (int) strtotime($timestamp);
        }

        return $timestamp;
    }
}
