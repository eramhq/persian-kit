<?php

namespace PersianKit\Tests\Integration\Support;

use PersianKit\Bootstrap;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\DateConversion\DateConversionModule;
use PersianKit\Modules\DateConversion\DateFilters;
use PersianKit\Modules\DateConversion\GregorianCompanion;
use PersianKit\Modules\DateConversion\JalaliArchiveList;
use PersianKit\Modules\DateConversion\JalaliCalendar;

trait BootsDateConversion
{
    /**
     * Boot the Date Conversion module again with these settings, without the
     * archive list and calendar the plugin registered at load. Hooks added
     * here are removed after the test.
     *
     * @param array<string, mixed> $values
     */
    private function bootDateConversionWith(array $values): void
    {
        $list = Bootstrap::get(JalaliArchiveList::class);
        remove_filter('getarchives_where', [$list, 'captureWhere'], PHP_INT_MAX);
        remove_filter('getarchives_join', [$list, 'captureJoin'], PHP_INT_MAX);
        remove_filter('get_archives_link', [$list, 'filterArchivesLink'], PHP_INT_MAX);
        remove_filter('get_calendar', [Bootstrap::get(JalaliCalendar::class), 'filterCalendar']);

        update_option('persian_kit_settings', [
            DateConversionModule::key() => array_replace(DateConversionModule::defaults(), $values),
        ]);

        $settings = new SettingsManager();
        $settings->registerDefaults(DateConversionModule::key(), DateConversionModule::defaults());

        (new DateConversionModule($settings))->boot(Bootstrap::container());
    }

    /**
     * Swap the plugin's date filters for ones with "Show the Gregorian date
     * too" on. The container keeps the filters it built at load, so they are
     * replaced here rather than booted again.
     */
    private function showGregorianToo(string $style = 'numeric', string $order = 'jalali_first', string $separator = 'parentheses'): void
    {
        foreach ($GLOBALS['wp_filter'] as $hook => $filter) {
            foreach ($filter->callbacks as $priority => $callbacks) {
                foreach ($callbacks as $callback) {
                    if (is_array($callback['function']) && $callback['function'][0] instanceof DateFilters) {
                        remove_filter($hook, $callback['function'], $priority);
                    }
                }
            }
        }

        (new DateFilters(false, new GregorianCompanion(true, $style, $order, $separator)))->registerTier1();
    }

    /**
     * A numeric Gregorian date as the page gets it: between invisible
     * left-to-right isolate marks.
     */
    private static function ltr(string $date): string
    {
        return "\u{2066}" . $date . "\u{2069}";
    }
}
