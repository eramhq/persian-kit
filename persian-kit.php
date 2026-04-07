<?php
/**
 * Plugin Name: Persian Kit
 * Plugin URI: https://github.com/flavor-dev/persian-kit
 * Description: A modular Persian (Farsi) language toolkit for WordPress.
 * Version: 0.9.0
 * Author: Navid Kashani
 * Author URI: https://flavor.dev
 * Text Domain: persian-kit
 * Domain Path: /languages
 * Requires at least: 6.2
 * Requires PHP: 8.1
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Plugin Prefix: PERSIAN_KIT
 */

defined('ABSPATH') || exit;

/*
|--------------------------------------------------------------------------
| Autoloader
|--------------------------------------------------------------------------
*/

// In production, wp-scoper generates packages/autoload.php.
// In development, Composer's vendor/autoload.php is used instead.
$composerAutoload = __DIR__ . '/packages/autoload.php';
if (!file_exists($composerAutoload)) {
    $composerAutoload = __DIR__ . '/vendor/autoload.php';
}
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

/*
|--------------------------------------------------------------------------
| Constants
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/src/constants.php';

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/
require_once __DIR__ . '/src/functions.php';

/*
|--------------------------------------------------------------------------
| Bootstrap
|--------------------------------------------------------------------------
*/
PersianKit\Bootstrap::init();
