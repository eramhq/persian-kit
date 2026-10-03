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

    // Plugins the integration tests cover: WooCommerce, Contact Form 7, ACF,
    // Forminator, Yoast SEO, Rank Math and Polylang. Each loads from
    // PERSIAN_KIT_TESTS_<NAME>_DIR (as in CI), or when installed next to the
    // plugin. Their tests are skipped without them; set
    // PERSIAN_KIT_TESTS_WITHOUT_<NAME>=1 to run the suite without one.
    $plugins = [
        'WOOCOMMERCE' => 'woocommerce/woocommerce.php',
        'CF7'         => 'contact-form-7/wp-contact-form-7.php',
        'ACF'         => 'advanced-custom-fields/acf.php',
        'FORMINATOR'  => 'forminator/forminator.php',
        'YOAST'       => 'wordpress-seo/wp-seo.php',
        'RANK_MATH'   => 'seo-by-rank-math/rank-math.php',
    ];
    // Polylang filters every query by language once it has languages, so
    // it loads only for the multilingual run (composer test:integration:polylang).
    if (getenv('PERSIAN_KIT_TESTS_MULTILINGUAL')) {
        $plugins['POLYLANG'] = 'polylang/polylang.php';
    }
    // The plugins a site can switch from, for the tests that check Persian
    // Kit against them (composer test:integration:sources). Parsi Date
    // converts every date once active, so they load only for that run.
    if (getenv('PERSIAN_KIT_TESTS_SOURCES')) {
        $plugins['PARSIDATE'] = 'wp-parsidate/wp-parsidate.php';
        $plugins['PERSIAN_WOOCOMMERCE'] = 'persian-woocommerce/woocommerce-persian.php';
        $plugins['PWS'] = 'persian-woocommerce-shipping/woocommerce-shipping.php';
    }
    $pluginFiles = [];
    foreach ($plugins as $name => $file) {
        $dir = getenv("PERSIAN_KIT_TESTS_{$name}_DIR") ?: dirname(__DIR__, 2) . '/' . dirname($file);
        $path = rtrim($dir, '/') . '/' . basename($file);
        if (file_exists($path) && !getenv("PERSIAN_KIT_TESTS_WITHOUT_{$name}")) {
            $pluginFiles[$name] = $path;
        }
    }
    $loadWooCommerce = isset($pluginFiles['WOOCOMMERCE']);

    // The plugins loaded here count as active, for is_plugin_active() checks
    // such as Rank Math's for WooCommerce.
    tests_add_filter('option_active_plugins', function ($active) use ($plugins, $pluginFiles) {
        return array_values(array_unique(array_merge((array) $active, array_values(array_intersect_key($plugins, $pluginFiles)))));
    });

    // Rank Math does nothing on the front end until its account step is skipped.
    if (isset($pluginFiles['RANK_MATH']) && !defined('RANK_MATH_REGISTRATION_SKIP')) {
        define('RANK_MATH_REGISTRATION_SKIP', true);
    }

    tests_add_filter('muplugins_loaded', function () use ($pluginFiles) {
        // The Rank Math modules the tests cover, before it loads them.
        if (isset($pluginFiles['RANK_MATH'])) {
            update_option('rank_math_modules', ['sitemap', 'rich-snippet', 'woocommerce']);
        }

        // Parsi Date with Jalali dates and Jalali post links, as most sites use it.
        if (isset($pluginFiles['PARSIDATE'])) {
            update_option('wp_parsidate', ['persian_date' => true, 'conv_permalinks' => true]);
        }

        foreach ($pluginFiles as $path) {
            require $path;
        }

        // Yoast SEO and Rank Math keep the page they describe for the rest of
        // the request; each go_to() is a new one.
        add_action('wp', function () {
            if (function_exists('YoastSEO')) {
                YoastSEO()->classes->get(\Yoast\WP\SEO\Memoizers\Meta_Tags_Context_Memoizer::class)->clear();
            }
            if (class_exists(\RankMath\Paper\Paper::class)) {
                \RankMath\Paper\Paper::reset();
            }
        }, 0);

        require dirname(__DIR__) . '/persian-kit.php';
    });

    if (isset($pluginFiles['POLYLANG'])) {
        // Without languages Polylang loads no context and no API. The tests
        // create them (UsesPolylang) and read pages as the front end does.
        tests_add_filter('pll_context', static fn (): string => 'PLL_Frontend');
    }

    if ($loadWooCommerce) {
        // The test install drops every table, so WooCommerce creates its own again.
        tests_add_filter('setup_theme', function () {
            \WC_Install::install();
            $GLOBALS['wp_roles'] = null;
            wp_roles();
        });
    }

    if (isset($pluginFiles['FORMINATOR'])) {
        // Forminator's entry tables, as on activation, once it has loaded.
        tests_add_filter('init', static function () {
            \Forminator_Database_Tables::install_database_tables();
        }, 99);
    }

    if (isset($pluginFiles['RANK_MATH'])) {
        // Its other defaults (titles, sitemaps), as on activation.
        tests_add_filter('setup_theme', function () {
            (new \RankMath\Installer())->activation(false);

            // Rank Math sends date archives to the home page by default;
            // the archive tests need them.
            $titles = get_option('rank-math-options-titles', []);
            update_option('rank-math-options-titles', ['disable_date_archives' => 'off'] + $titles);
            // It read its settings before they existed.
            rank_math()->settings->reset();
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
