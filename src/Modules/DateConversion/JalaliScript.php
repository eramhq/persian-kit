<?php

namespace PersianKit\Modules\DateConversion;

defined('ABSPATH') || exit;

/**
 * Registers the Jalali calendar script (public/js/jalali.js), the
 * translated labels of the block editor's date editor, which is built on it,
 * and the month and weekday names of this request's set (CalendarNames).
 * The other admin date fields use the date picker (DatePicker).
 */
final class JalaliScript
{
    public const HANDLE = 'persian-kit-jalali';

    public static function register(): void
    {
        if (wp_script_is(self::HANDLE, 'registered')) {
            return;
        }

        wp_register_script(
            self::HANDLE,
            PERSIAN_KIT_URL . 'public/js/jalali.js',
            [],
            PERSIAN_KIT_VERSION,
            true
        );

        wp_add_inline_script(
            self::HANDLE,
            'window.persianKitDateLabels = ' . wp_json_encode([
                'year'   => __('Year', 'persian-kit'),
                'month'  => __('Month', 'persian-kit'),
                'day'    => __('Day', 'persian-kit'),
                'hour'   => __('Hour', 'persian-kit'),
                'minute' => __('Minute', 'persian-kit'),
                'names'  => self::names(),
            ]) . ';',
            'before'
        );
    }

    /**
     * The current set's names as jalali.js reads them: months from index 1,
     * weekdays from Sunday, seasons from spring.
     *
     * @return array{months: list<string>, monthsShort: list<string>, weekdays: list<string>, weekdaysShort: list<string>, seasons: list<string>, ordinal: string}
     */
    public static function names(): array
    {
        $names = CalendarNames::current();

        return [
            'months'        => array_merge([''], array_values($names['months'])),
            'monthsShort'   => array_merge([''], array_values($names['months_short'])),
            'weekdays'      => array_values($names['weekdays']),
            'weekdaysShort' => array_values($names['weekdays_short']),
            'seasons'       => $names['seasons'],
            'ordinal'       => $names['ordinal'],
        ];
    }
}
