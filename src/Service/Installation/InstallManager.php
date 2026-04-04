<?php

namespace PersianKit\Service\Installation;

use PersianKit\Core\SettingsManager;
use PersianKit\Modules\DigitConversion\DigitConversionModule;
use PersianKit\Modules\DateConversion\DateConversionModule;

defined('ABSPATH') || exit;

class InstallManager
{
    /** @var array<class-string<\PersianKit\Contracts\ModuleInterface>> */
    private static array $modules = [
        DigitConversionModule::class,
        DateConversionModule::class,
        // Future modules will be added here as they are built:
        // \PersianKit\Modules\AdminFont\AdminFontModule::class,
        // \PersianKit\Modules\CharNormalization\CharNormalizationModule::class,
        // \PersianKit\Modules\ZWNJEditor\ZWNJEditorModule::class,
        // \PersianKit\Modules\Utilities\UtilitiesModule::class,
    ];

    public static function activate(bool $networkWide): void
    {
        $settings = new SettingsManager();

        foreach (self::$modules as $moduleClass) {
            $settings->setDefaults($moduleClass::key(), $moduleClass::defaults());
        }
    }

    public static function deactivate(): void
    {
        // Cleanup on deactivation (clear transients, etc.)
    }
}
