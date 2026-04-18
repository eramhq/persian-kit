<?php
/**
 * Plugin Name: Persian Kit
 * Plugin URI: https://github.com/eramhq/persian-kit
 * Description: A modular Persian (Farsi) language toolkit for WordPress.
 * Version: 1.0.0-beta.1
 * Author: Navid Kashani
 * Author URI: https://flavor.dev
 * Text Domain: persian-kit
 * Domain Path: /languages
 * Requires at least: 6.5
 * Tested up to: 6.7
 * Requires PHP: 8.1
 * WC requires at least: 8.0
 * WC tested up to: 10.6.2
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
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

add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            PERSIAN_KIT_MAIN_FILE,
            true
        );
    }
});

/*
|--------------------------------------------------------------------------
| Bootstrap
|--------------------------------------------------------------------------
*/
PersianKit\Bootstrap::init();
