<?php

namespace PersianKit;

use PersianKit\Container\ServiceContainer;
use PersianKit\Core\CoreServiceProvider;
use PersianKit\Service\Installation\InstallManager;

defined('ABSPATH') || exit;

class Bootstrap
{
    private static bool $initialized = false;

    /** @var array<class-string<\PersianKit\Container\ServiceProvider>> */
    private static array $providers = [
        CoreServiceProvider::class,
    ];

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        self::registerLifecycleHooks();

        add_action('plugins_loaded', [__CLASS__, 'setup'], 10);
    }

    public static function setup(): void
    {
        add_action('init', [__CLASS__, 'loadTextdomain']);

        self::initializeServices();

        do_action('persian_kit_loaded');
    }

    public static function container(): ServiceContainer
    {
        return ServiceContainer::getInstance();
    }

    public static function get(string $id): mixed
    {
        return self::container()->get($id);
    }

    public static function loadTextdomain(): void
    {
        load_plugin_textdomain(
            'persian-kit',
            false,
            dirname(plugin_basename(PERSIAN_KIT_MAIN_FILE)) . '/languages'
        );
    }

    public static function activate(bool $networkWide): void
    {
        InstallManager::activate($networkWide);
    }

    public static function deactivate(): void
    {
        InstallManager::deactivate();
    }

    private static function registerLifecycleHooks(): void
    {
        register_activation_hook(PERSIAN_KIT_MAIN_FILE, [__CLASS__, 'activate']);
        register_deactivation_hook(PERSIAN_KIT_MAIN_FILE, [__CLASS__, 'deactivate']);
    }

    private static function initializeServices(): void
    {
        $container = self::container();

        $providers = [];
        foreach (self::$providers as $providerClass) {
            $provider = new $providerClass();
            $provider->register($container);
            $providers[] = $provider;
        }

        foreach ($providers as $provider) {
            $provider->boot($container);
        }
    }
}
