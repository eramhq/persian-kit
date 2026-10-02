<?php

namespace PersianKit\Modules\Forms;

use PersianKit\Modules\DateConversion\DateConversionModule;

defined('ABSPATH') || exit;

/**
 * For form modules: dates are shown as Jalali dates where people read them,
 * such as in emails and field values in templates, only while Date
 * Conversion is on.
 */
trait ShowsJalaliDates
{
    protected function showsJalaliDates(): bool
    {
        return (bool) $this->settings->module(DateConversionModule::key(), 'enabled', true);
    }
}
