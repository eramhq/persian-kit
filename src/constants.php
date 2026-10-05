<?php

defined('ABSPATH') || exit;

$persian_kit_dir = dirname(__DIR__);

if (!defined('PERSIAN_KIT_VERSION')) {
    define('PERSIAN_KIT_VERSION', '1.0.0');
}

if (!defined('PERSIAN_KIT_URL')) {
    define('PERSIAN_KIT_URL', plugin_dir_url($persian_kit_dir . '/persian-kit.php'));
}

if (!defined('PERSIAN_KIT_DIR')) {
    define('PERSIAN_KIT_DIR', $persian_kit_dir . '/');
}

if (!defined('PERSIAN_KIT_MAIN_FILE')) {
    define('PERSIAN_KIT_MAIN_FILE', PERSIAN_KIT_DIR . 'persian-kit.php');
}

unset($persian_kit_dir);
