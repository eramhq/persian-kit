<?php

namespace PersianKit\Service\Installation;

use PersianKit\Core\SettingsManager;

defined('ABSPATH') || exit;

class InstallManager
{
    public static function activate(bool $networkWide): void
    {
        $settings = new SettingsManager();

        // Set smart defaults — sensible modules auto-enable on activation
        $settings->setDefaults('date_conversion', ['enabled' => true]);
        $settings->setDefaults('digit_conversion', ['enabled' => true]);
        $settings->setDefaults('admin_font', ['enabled' => true, 'font' => 'vazirmatn']);
        $settings->setDefaults('char_normalization', ['enabled' => true, 'teh_marbuta' => false]);
        $settings->setDefaults('zwnj_editor', ['enabled' => true]);
        $settings->setDefaults('utilities', ['enabled' => true]);
    }

    public static function deactivate(): void
    {
        // Cleanup on deactivation (clear transients, etc.)
    }
}
