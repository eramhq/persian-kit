<?php

// Load Composer autoloader.
$autoloader = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($autoloader)) {
    require_once $autoloader;
}

// Load WordPress test library if available.
$wpTestsDir = getenv('WP_TESTS_DIR');

if (!$wpTestsDir) {
    $wpTestsDir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

if (file_exists($wpTestsDir . '/includes/functions.php')) {
    $phpunitPolyfillsPath = dirname(__DIR__) . '/vendor/yoast/phpunit-polyfills';
    if (file_exists($phpunitPolyfillsPath . '/phpunitpolyfills-autoload.php') && !defined('WP_TESTS_PHPUNIT_POLYFILLS_PATH')) {
        define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', $phpunitPolyfillsPath);
    }

    require_once $wpTestsDir . '/includes/functions.php';

    // Plugins the integration tests cover: WooCommerce, Contact Form 7, ACF. Each
    // loads from PERSIAN_KIT_TESTS_<NAME>_DIR (as in CI), or when installed
    // next to the plugin. Their tests are skipped without them; set
    // PERSIAN_KIT_TESTS_WITHOUT_<NAME>=1 to run the suite without one.
    $plugins = [
        'WOOCOMMERCE' => 'woocommerce/woocommerce.php',
        'CF7'         => 'contact-form-7/wp-contact-form-7.php',
        'ACF'         => 'advanced-custom-fields/acf.php',
    ];
    $pluginFiles = [];
    foreach ($plugins as $name => $file) {
        $dir = getenv("PERSIAN_KIT_TESTS_{$name}_DIR") ?: dirname(__DIR__, 2) . '/' . dirname($file);
        $path = rtrim($dir, '/') . '/' . basename($file);
        if (file_exists($path) && !getenv("PERSIAN_KIT_TESTS_WITHOUT_{$name}")) {
            $pluginFiles[$name] = $path;
        }
    }
    $loadWooCommerce = isset($pluginFiles['WOOCOMMERCE']);

    tests_add_filter('muplugins_loaded', function () use ($pluginFiles) {
        foreach ($pluginFiles as $path) {
            require $path;
        }

        require dirname(__DIR__) . '/persian-kit.php';
    });

    if ($loadWooCommerce) {
        // The test install drops every table, so WooCommerce creates its own again.
        tests_add_filter('setup_theme', function () {
            \WC_Install::install();
            $GLOBALS['wp_roles'] = null;
            wp_roles();
        });
    }

    require $wpTestsDir . '/includes/bootstrap.php';
} else {
    // Standalone mode — define ABSPATH so guarded files can load.
    if (!defined('ABSPATH')) {
        define('ABSPATH', '/');
    }

    // Load scoped packages autoloader for unit tests.
    $packagesAutoload = dirname(__DIR__) . '/packages/autoload.php';
    if (file_exists($packagesAutoload)) {
        require_once $packagesAutoload;
    }

    require_once __DIR__ . '/Unit/Support/wp-stubs.php';
}
