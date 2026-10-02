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

    // WooCommerce for the checkout tests: from PERSIAN_KIT_TESTS_WOOCOMMERCE_DIR
    // (as in CI), or installed next to the plugin. Those tests are skipped
    // without it; set PERSIAN_KIT_TESTS_WITHOUT_WOOCOMMERCE=1 to run the suite without it.
    $woocommerceDir = getenv('PERSIAN_KIT_TESTS_WOOCOMMERCE_DIR') ?: dirname(__DIR__, 2) . '/woocommerce';
    $woocommerceFile = rtrim($woocommerceDir, '/') . '/woocommerce.php';
    $loadWooCommerce = file_exists($woocommerceFile) && !getenv('PERSIAN_KIT_TESTS_WITHOUT_WOOCOMMERCE');

    tests_add_filter('muplugins_loaded', function () use ($loadWooCommerce, $woocommerceFile) {
        if ($loadWooCommerce) {
            require $woocommerceFile;
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
