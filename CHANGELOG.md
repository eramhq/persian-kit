# Changelog

All notable changes to this project will be documented in this file.

## [X.Y.Z] - Unreleased

- Prepared the plugin for WordPress.org submission: added `readme.txt`, `uninstall.php`, and `Tested up to:` plugin header.
- Bumped `Requires at least:` to WordPress 6.5 to match `wp_enqueue_script_module()` usage.
- Declared WooCommerce HPOS compatibility via `before_woocommerce_init`.
- Removed `load_plugin_textdomain()` call; translations are auto-loaded by WordPress core for hosted plugins.
- Replaced the inline `<style>` in the WooCommerce orders Jalali month filter with a conditionally enqueued stylesheet.
- Renamed the admin bundle from `admin.min.js` to `admin.js` and disabled minification so the source-equivalent ships in the dist.
- Removed the non-standard `Plugin Prefix:` header.
- Tightened `.distignore` against AI-tooling directories and OS metadata files.

## [1.0.0-beta.1] - 2026-04-17

- Replaced the plugin's internal Persian utility classes with the `eram/abzar` library.
- Added `pk_*` helpers for the new features: `pk_currency_format`, `pk_currency_convert`, `pk_words_to_number`, `pk_validate_postal_code`, `pk_validate_plate_number`, `pk_validate_bill_id`, `pk_half_space_fix`, `pk_keyboard_fix`, `pk_persian_sort`.
- Pre-release — unannounced API; expect churn until 1.0.0.

## [0.9.0] - 2026-04-07

Initial pre-1.0 public baseline.

- Added modular settings-driven plugin architecture
- Added Jalali date conversion for core WordPress display contexts
- Added REST API Jalali companion fields
- Added Persian digit conversion for content-facing text
- Added Arabic-to-Persian character normalization on save and search
- Added normalization batch processing, REST controller, and WP-CLI command
- Added Vazirmatn-based admin font support
- Added ZWNJ editor shortcuts for Classic Editor and Gutenberg
- Added WooCommerce Jalali date support for supported admin and display contexts
- Added Persian utility helpers for validation, formatting, slugs, script detection, ordinals, and time-ago text
- Added unit and integration test coverage
- Added repository documentation for development and reference usage
