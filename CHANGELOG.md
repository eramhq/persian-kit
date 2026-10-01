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

### Library upgrade: eram/abzar 0.8, eram/daynum 1.0.0-beta.3

- Upgraded `eram/abzar` from 0.5 to `^0.8@beta` and `eram/daynum` to 1.0.0-beta.3.
- Persian slugs: a ZWNJ in a title now becomes `-` (`می‌خواهم` → `می-خواهم`), and Persian punctuation, kashida and tashkeel are dropped. Slugs saved by earlier versions with a ZWNJ still resolve.
- Persian slug handling now applies only to titles that contain Persian/Arabic letters. Other titles go through WordPress core again, which fixes WooCommerce attribute taxonomies (`pa_color` was turned into `pa-color`), percent-encoded slugs and accented Latin letters (`Café` → `cafe`).
- Posts created before the plugin was activated (stored with WordPress's percent-encoded slugs) load again. A request whose slug only differs in ZWNJ vs `-` is redirected (301) to the post.
- Digit conversion and character normalization leave content unchanged instead of failing when PCRE cannot process very large or malformed HTML. The batch normalizer skips such posts and continues.
- `pk_ordinal_short()` takes `bool $persianDigits = true` as its second argument. The old `'persian'` / `'english'` strings still work.
- Behaviour changes in the `pk_*` helpers that come from abzar:
  - `pk_keyboard_fix()` maps upper-case Latin letters through the Persian Shift layer (`H` → `آ`) instead of lower-casing them.
  - Validators report a warning for unknown national-ID city codes, IBAN bank codes and phone area codes; `isValid()` stays `true`. Use `isStrictlyValid()` to reject them.
  - Licence-plate province and plate-type data were corrected.
  - `pk_ordinal_word(30)` returns `سی‌ام` (was `سی اُم`).
  - `pk_words_to_number()` returns `null` for text that is not a number (`دو سه`) and for values past `PHP_INT_MAX`.

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
