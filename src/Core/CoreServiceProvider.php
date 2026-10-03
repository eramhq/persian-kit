<?php

namespace PersianKit\Core;

use PersianKit\Container\ServiceContainer;
use PersianKit\Container\ServiceProvider;
use PersianKit\Contracts\ModuleInterface;
use PersianKit\Service\Assets\AssetManager;
use PersianKit\Service\Import\ImportReview;
use PersianKit\Service\Import\ImportServices;
use PersianKit\Service\Language\ContentLanguage;

defined('ABSPATH') || exit;

class CoreServiceProvider implements ServiceProvider
{
    /**
     * Module instances, created in register() and booted in boot(). Bootstrap
     * boots the same provider instance it registered.
     *
     * @var list<ModuleInterface>
     */
    private array $modules = [];

    public function register(ServiceContainer $container): void
    {
        $container->register(SettingsManager::class, function () {
            return new SettingsManager();
        });

        $container->register(ConflictDetector::class, function (ServiceContainer $c) {
            return new ConflictDetector($c->get(SettingsManager::class));
        });

        $container->register(AssetManager::class, function () {
            return new AssetManager();
        });

        // Switching from other Persian plugins, on the Tools tab.
        ImportServices::register($container);

        // Instantiate and register each feature module
        $settings = $container->get(SettingsManager::class);

        foreach (ModuleRegistry::MODULES as $moduleClass) {
            $settings->registerDefaults($moduleClass::key(), $moduleClass::defaults());
            $module = new $moduleClass($settings);
            $module->register($container);
            $this->modules[] = $module;
        }

        $modules = $this->modules;

        $container->register(SettingsRegistrar::class, function () use ($modules) {
            return new SettingsRegistrar($modules);
        });

        $container->register(AdminPage::class, function (ServiceContainer $c) use ($modules) {
            return new AdminPage(
                $c->get(SettingsManager::class),
                $modules,
                $c->get(ConflictDetector::class),
                $c->get(ImportReview::class)
            );
        });
    }

    public function boot(ServiceContainer $container): void
    {
        // Conflict detection
        $container->get(ConflictDetector::class)->registerNotice();

        ImportServices::boot($container);

        // Sanitizes every write to the settings option, from any context.
        $container->get(SettingsRegistrar::class)->register();

        // The settings page, its assets and admin-post handlers. is_admin()
        // is true for admin-post.php and false for REST, cron and WP-CLI.
        if (is_admin()) {
            $container->get(AssetManager::class);
            $container->get(AdminPage::class)->register();
        }

        // Follows the language on WPML and Polylang sites, before the modules
        // that ask for it boot.
        ContentLanguage::register();

        // Boot each module whose plugins are active: fully while it is on,
        // and its fallback while it is off.
        foreach ($this->modules as $module) {
            if (!$module->isAvailable()) {
                continue;
            }

            if ($module->isEnabled()) {
                $module->boot($container);
            } else {
                $module->bootDisabled($container);
            }
        }
    }
}
