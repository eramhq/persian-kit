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

    /** @var list<\PersianKit\Container\ServiceProvider> */
    private static array $bootQueue = [];

    public static function init(): void
    {
        if (self::$initialized) {
            return;
        }

        self::$initialized = true;

        self::registerLifecycleHooks();

        add_action('plugins_loaded', [__CLASS__, 'setup'], 10);
    }

    /**
     * Services are registered on plugins_loaded and booted on after_setup_theme,
     * so filters a theme adds in functions.php are in place before any module
     * reads them.
     */
    public static function setup(): void
    {
        InstallManager::maybeUpgrade();

        self::registerServices();

        do_action('persian_kit_loaded');

        add_action('after_setup_theme', [__CLASS__, 'boot'], 20);
    }

    public static function boot(): void
    {
        $container = self::container();

        foreach (self::$bootQueue as $provider) {
            $provider->boot($container);
        }

        self::$bootQueue = [];
    }

    public static function container(): ServiceContainer
    {
        return ServiceContainer::getInstance();
    }

    public static function get(string $id): mixed
    {
        return self::container()->get($id);
    }

    public static function activate(bool $networkWide): void
    {
        InstallManager::activate($networkWide);
    }

    private static function registerLifecycleHooks(): void
    {
        register_activation_hook(PERSIAN_KIT_MAIN_FILE, [__CLASS__, 'activate']);
        add_action('wp_initialize_site', [InstallManager::class, 'initializeSite'], 20);
    }

    private static function registerServices(): void
    {
        $container = self::container();

        foreach (self::$providers as $providerClass) {
            $provider = new $providerClass();
            $provider->register($container);
            self::$bootQueue[] = $provider;
        }
    }
}
