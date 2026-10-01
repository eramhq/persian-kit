<?php

namespace PersianKit\Service\Installation;

use PersianKit\Core\SettingsManager;
use PersianKit\Modules\AdminFont\AdminFontModule;
use PersianKit\Modules\DateConversion\DateConversionModule;
use PersianKit\Modules\DigitConversion\DigitConversionModule;
use PersianKit\Modules\WooCommerce\WooCommerceModule;
use PersianKit\Modules\ZWNJEditor\ZWNJEditorModule;
use PersianKit\Modules\CharNormalization\CharNormalizationModule;
use PersianKit\Modules\Utilities\UtilitiesModule;

defined('ABSPATH') || exit;

class InstallManager
{
    public const DB_VERSION_OPTION = 'persian_kit_db_version';

    /** Set on a fresh install; the settings page shows the welcome notice until it is dismissed. */
    public const WELCOME_OPTION = 'persian_kit_show_welcome';

    /**
     * Bump when stored settings need a migration, and add the step to migrate().
     */
    public const DB_VERSION = 2;

    /** @var array<class-string<\PersianKit\Contracts\ModuleInterface>> */
    private static array $modules = [
        DigitConversionModule::class,
        DateConversionModule::class,
        CharNormalizationModule::class,
        AdminFontModule::class,
        ZWNJEditorModule::class,
        WooCommerceModule::class,
        UtilitiesModule::class,
    ];

    public static function activate(bool $networkWide): void
    {
        if (!$networkWide || !is_multisite()) {
            self::installSite();
            return;
        }

        foreach (get_sites(['fields' => 'ids', 'number' => 0]) as $siteId) {
            switch_to_blog((int) $siteId);
            self::installSite();
            restore_current_blog();
        }
    }

    /**
     * Sets up a site created while the plugin is network-active.
     */
    public static function initializeSite(\WP_Site $site): void
    {
        if (!function_exists('is_plugin_active_for_network')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        if (!is_plugin_active_for_network(plugin_basename(PERSIAN_KIT_MAIN_FILE))) {
            return;
        }

        switch_to_blog((int) $site->blog_id);
        self::installSite();
        restore_current_blog();
    }

    /**
     * Runs on every load; does nothing once the site is at DB_VERSION.
     */
    public static function maybeUpgrade(): void
    {
        if ((int) get_option(self::DB_VERSION_OPTION, 0) >= self::DB_VERSION) {
            return;
        }

        self::installSite();
    }

    /**
     * Writes the defaults on a fresh site, or migrates stored settings on a
     * site that already has them, then records the schema version.
     */
    public static function installSite(): void
    {
        $version = get_option(self::DB_VERSION_OPTION, false);
        $stored = get_option(SettingsManager::OPTION_KEY, []);
        $settings = new SettingsManager();

        if (!is_array($stored) || $stored === []) {
            $defaults = [];
            foreach (self::$modules as $moduleClass) {
                $defaults[$moduleClass::key()] = $moduleClass::defaults();
            }
            $settings->updateModules($defaults);
            update_option(self::WELCOME_OPTION, 1, false);
        } elseif ((int) $version < self::DB_VERSION) {
            self::migrate((int) $version, $settings);
        }

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION, true);
    }

    /**
     * Version 0 is a site set up before the schema version existed.
     */
    private static function migrate(int $fromVersion, SettingsManager $settings): void
    {
        if ($fromVersion < 2) {
            // Version 2 made new installs start with digit conversion and
            // normalize-on-save off. Existing sites keep what they had: write
            // the old behaviour out explicitly so the new defaults don't apply.
            $settings->updateModules([
                DigitConversionModule::key() => array_replace(
                    ['enabled' => true, 'dates' => false, 'numbers' => false, 'prices' => false],
                    $settings->module(DigitConversionModule::key())
                ),
                CharNormalizationModule::key() => array_replace(
                    ['enabled' => true, 'normalize_on_save' => true, 'teh_marbuta' => false],
                    $settings->module(CharNormalizationModule::key())
                ),
            ]);
        }
    }
}
