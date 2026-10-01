<?php

namespace PersianKit\Core;

use PersianKit\Modules\AdminFont\AdminFontModule;
use PersianKit\Modules\CharNormalization\CharNormalizationModule;
use PersianKit\Modules\DateConversion\DateConversionModule;
use PersianKit\Modules\DigitConversion\DigitConversionModule;
use PersianKit\Modules\Utilities\UtilitiesModule;
use PersianKit\Modules\WooCommerce\WooCommerceModule;
use PersianKit\Modules\ZWNJEditor\ZWNJEditorModule;

defined('ABSPATH') || exit;

/**
 * Every feature module, in the order they register, boot and appear on the
 * settings page.
 */
final class ModuleRegistry
{
    /** @var list<class-string<\PersianKit\Contracts\ModuleInterface>> */
    public const MODULES = [
        DigitConversionModule::class,
        DateConversionModule::class,
        CharNormalizationModule::class,
        AdminFontModule::class,
        ZWNJEditorModule::class,
        WooCommerceModule::class,
        UtilitiesModule::class,
    ];
}
