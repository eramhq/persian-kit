<?php

namespace PersianKit\Tests\Integration\Support;

use PersianKit\Bootstrap;
use PersianKit\Core\SettingsManager;
use PersianKit\Modules\DateConversion\DateConversionModule;
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
}
