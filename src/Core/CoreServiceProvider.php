<?php

namespace PersianKit\Core;

use PersianKit\Container\ServiceContainer;
use PersianKit\Container\ServiceProvider;
use PersianKit\Service\Assets\AssetManager;

defined('ABSPATH') || exit;

class CoreServiceProvider implements ServiceProvider
{
    /** @var array<class-string<\PersianKit\Contracts\ModuleInterface>> */
    private array $featureModules = [
        \PersianKit\Modules\DigitConversion\DigitConversionModule::class,
        \PersianKit\Modules\DateConversion\DateConversionModule::class,
        \PersianKit\Modules\CharNormalization\CharNormalizationModule::class,
        \PersianKit\Modules\AdminFont\AdminFontModule::class,
        \PersianKit\Modules\ZWNJEditor\ZWNJEditorModule::class,
        \PersianKit\Modules\Utilities\UtilitiesModule::class,
    ];

    public function register(ServiceContainer $container): void
    {
        $container->register(SettingsManager::class, function () {
            return new SettingsManager();
        });

        $container->register(ConflictDetector::class, function () {
            return new ConflictDetector();
        });

        $container->register(AssetManager::class, function () {
            return new AssetManager();
        });

        // Instantiate and register each feature module
        $modules = [];
        $settings = $container->get(SettingsManager::class);

        foreach ($this->featureModules as $moduleClass) {
            $module = new $moduleClass($settings);
            $module->register($container);
            $modules[] = $module;
        }

        $container->singleton('modules', (object) ['all' => $modules]);

        $container->register(AdminPage::class, function (ServiceContainer $c) use ($modules) {
            return new AdminPage(
                $c->get(SettingsManager::class),
                $modules
            );
        });
    }

    public function boot(ServiceContainer $container): void
    {
        // Conflict detection
        $container->get(ConflictDetector::class)->registerNotice();

        // Asset manager (enqueues admin scripts on plugin pages)
        $container->get(AssetManager::class);

        // Admin page
        $container->get(AdminPage::class)->register();

        // Boot each enabled feature module
        $modules = $container->get('modules')->all;
        foreach ($modules as $module) {
            if ($module->isEnabled()) {
                $module->boot($container);
            }
        }
    }
}
