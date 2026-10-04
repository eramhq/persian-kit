<?php

namespace PersianKit\Modules\Forms;

use PersianKit\Modules\DateConversion\DateDisplayGuard;
use PersianKit\Modules\DateConversion\JalaliFormatter;

defined('ABSPATH') || exit;

/**
 * Jalali dates on Gravity Forms' Entries screens: the list's Entry Date and
 * Payment Date columns, an entry's "Submitted on" and "Updated", and the
 * dates of its notes. Gravity Forms has no filter for them, and writes them
 * with date_i18n(), so that is filtered on these screens only, in the
 * admin's language.
 *
 * Each date is formatted again from its timestamp, so it reads the same
 * when Date Conversion already converts every date. Formats code reads,
 * such as the export's Y-m-d H:i:s, stay Gregorian (DateDisplayGuard).
 */
class GravityFormsEntryDates
{
    public function register(): void
    {
        add_action('current_screen', [$this, 'detectScreen']);
    }

    /**
     * @param mixed $screen WP_Screen
     */
    public function detectScreen($screen): void
    {
        // The screen id starts with the menu's title, which is translated.
        if (is_object($screen) && is_string($screen->id ?? null) && str_ends_with($screen->id, '_page_gf_entries')) {
            add_filter('date_i18n', [$this, 'jalaliDate'], 10, 4);
        }
    }

    /**
     * @param mixed $date
     * @param mixed $format
     * @param mixed $timestamp The local time as a timestamp, as date_i18n() takes it.
     * @param mixed $gmt
     * @return mixed
     */
    public function jalaliDate($date, $format = '', $timestamp = 0, $gmt = false)
    {
        if (!is_string($date) || !is_string($format) || !is_numeric($timestamp) || DateDisplayGuard::shouldBypass($format)) {
            return $date;
        }

        return JalaliFormatter::fromOffsetTimestamp($format, (int) $timestamp, (bool) $gmt);
    }
}
