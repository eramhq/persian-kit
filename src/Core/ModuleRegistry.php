<?php

namespace PersianKit\Core;

use PersianKit\Modules\AdminFont\AdminFontModule;
use PersianKit\Modules\CharNormalization\CharNormalizationModule;
use PersianKit\Modules\DateConversion\DateConversionModule;
use PersianKit\Modules\DigitConversion\DigitConversionModule;
use PersianKit\Modules\Forms\AcfModule;
use PersianKit\Modules\Forms\Cf7Module;
use PersianKit\Modules\Multilingual\PolylangModule;
use PersianKit\Modules\Multilingual\WpmlModule;
use PersianKit\Modules\Seo\RankMathModule;
use PersianKit\Modules\Seo\YoastModule;
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
        Cf7Module::class,
        AcfModule::class,
        YoastModule::class,
        RankMathModule::class,
        WpmlModule::class,
        PolylangModule::class,
        UtilitiesModule::class,
    ];
}
