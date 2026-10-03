<?php

namespace PersianKit\Service\Import\Sources\ParsiDate;

use PersianKit\Service\Import\AbstractSource;

defined('ABSPATH') || exit;

/**
 * Parsi Date (wp-parsidate): 6.x keeps its settings in wp_parsidate and
 * wp_parsidate_{woocommerce,acf,edd}; 5.x kept them all in wpp_settings.
 */
class ParsiDateSource extends AbstractSource
{
    public const KEY = 'wp-parsidate';

    /** Widget options of its archive and calendar widgets, in 6.4, 6.0 to 6.3 and 5.x. */
    public const WIDGET_OPTIONS = [
        'widget_wp_parsidate_archive',
        'widget_wp_parsidate_calendar',
        'widget_wpparsidate\widget\parsidatearchivewidget',
        'widget_wpparsidate\widget\parsidatecalendarwidget',
        'widget_parsidate_archive',
        'widget_parsidate_calendar',
    ];

    public function key(): string
    {
        return self::KEY;
    }

    public function name(): string
    {
        return __('Parsi Date', 'persian-kit');
    }

    public function pluginFile(): string
    {
        return 'wp-parsidate/wp-parsidate.php';
    }

    public function hasData(): bool
    {
        if (self::arrayOption('wp_parsidate') !== [] || self::arrayOption('wpp_settings') !== []) {
            return true;
        }

        foreach (self::WIDGET_OPTIONS as $option) {
            if (array_filter(self::arrayOption($option), 'is_array') !== []) {
                return true;
            }
        }

        return false;
    }

    protected function isLoaded(): bool
    {
        return defined('WP_PARSI_ROOT');
    }
}
