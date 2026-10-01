<?php

namespace PersianKit\Modules\DateConversion;

defined('ABSPATH') || exit;

/**
 * Registers the shared Jalali calendar script (public/js/jalali.js) and the
 * translated labels the date editors built on it use.
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
            ]) . ';',
            'before'
        );
    }
}
