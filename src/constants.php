<?php

defined('ABSPATH') || exit;

$pluginDir = dirname(__DIR__);

if (!defined('PERSIAN_KIT_VERSION')) {
    define('PERSIAN_KIT_VERSION', '1.0.0-beta.1');
}

if (!defined('PERSIAN_KIT_URL')) {
    define('PERSIAN_KIT_URL', plugin_dir_url($pluginDir . '/persian-kit.php'));
}

if (!defined('PERSIAN_KIT_DIR')) {
    define('PERSIAN_KIT_DIR', $pluginDir . '/');
}

if (!defined('PERSIAN_KIT_MAIN_FILE')) {
    define('PERSIAN_KIT_MAIN_FILE', PERSIAN_KIT_DIR . 'persian-kit.php');
}

unset($pluginDir);
