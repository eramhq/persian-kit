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
    require_once $wpTestsDir . '/includes/functions.php';

    tests_add_filter('muplugins_loaded', function () {
        require dirname(__DIR__) . '/persian-kit.php';
    });

    require $wpTestsDir . '/includes/bootstrap.php';
} else {
    // Standalone mode — define ABSPATH so guarded files can load.
    if (!defined('ABSPATH')) {
        define('ABSPATH', '/');
    }
}
