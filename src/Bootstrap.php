<?php

namespace PersianKit;

use PersianKit\Container\ServiceContainer;
use PersianKit\Core\CoreServiceProvider;
use PersianKit\Modules\CharNormalization\CLI\NormalizeCommand;
use PersianKit\Service\I18n\BundledTranslations;
use PersianKit\Service\Import\Cli\ImportCommand;
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
        BundledTranslations::register();

        add_action('plugins_loaded', [__CLASS__, 'setup'], 10);

        if (defined('WP_CLI') && WP_CLI) {
            add_action('cli_init', [__CLASS__, 'registerCliCommands']);
        }
    }

    /**
     * Available whether or not the Character Normalization module, or any
     * other, is on.
     * The class name (not an instance) keeps WP-CLI's docblock synopsis, and
     * WP-CLI only constructs the command when it runs.
     */
    public static function registerCliCommands(): void
    {
        \WP_CLI::add_command('persian-kit normalize', NormalizeCommand::class);
        \WP_CLI::add_command('persian-kit import', ImportCommand::class);
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
