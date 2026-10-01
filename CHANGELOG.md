# Changelog

All notable changes to this project will be documented in this file.

## [1.0.0] - Unreleased

- Prepared the plugin for WordPress.org submission: added `readme.txt`, `uninstall.php`, and `Tested up to:` plugin header.
- Bumped `Requires at least:` to WordPress 6.5 (for `wp_is_serving_rest_request()`).
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

### Date correctness

- RSS, Atom and other feeds show Gregorian dates again. Every date filter steps aside inside feeds, and `Y-m-d H:i:s`, `Y-m-d\TH:i:s\Z`, `G` and `U` are never converted.
- Post and comment dates are read in the site timezone. A comment at 23:00 no longer moves to the next day, and post times match `wp_date()`.
- Drafts (whose GMT date is `0000-00-00 00:00:00`) no longer show year −1. A date that cannot be parsed keeps WordPress's own output instead of turning into 1970.
- Dates passed through `date_i18n` (the dashboard Activity widget, WooCommerce) no longer get the timezone offset added twice.
- `get_post_time()` only converts the date of the current post, and honours `$gmt`.
- `the_date()` prints the date once per day again.
- The REST `date_jalali` field uses the local date for drafts, and media library dates use the site timezone.
- An out-of-range Jalali month in the WooCommerce order filter is ignored instead of causing a fatal error.

### Scope, robustness and performance

- Digit conversion no longer runs on admin screens (front-end AJAX still converts), in REST responses, feeds, or content filtered while `wp_mail` is being sent. Callbacks accept `null`.
- Digit conversion renames copies of terms instead of the cached `WP_Term` objects, and no longer runs twice on excerpts (`the_excerpt` is dropped; `get_the_excerpt` covers it).
- WooCommerce orders: the Jalali month filter only changes the HPOS orders list query, not every `wc_get_orders()` call. Each orders screen shows a single month select: WooCommerce's Gregorian drop-down is replaced on HPOS, and the legacy screen uses the shared post-type filter. The oldest-order lookup asks for an ID only.
- WooCommerce date display decides its context from the current screen and from WooCommerce template and order-confirmation block rendering, instead of calling `debug_backtrace()` on every `date_i18n()`.
- The Jalali month list on post screens caches its `SELECT DISTINCT DATE(post_date)` query until posts change.
- The admin font loads only for Persian or right-to-left admin languages.
- Batch normalization: opening the settings page no longer resumes an unfinished job automatically; it shows a Resume button instead. `wp persian-kit normalize` records its progress in the same job, so the settings page shows it, and `--batch-size` is clamped to 1–500.

### WordPress.org readiness

- Uninstalling deletes the options the plugin actually uses (`persian_kit_settings`, `persian_kit_normalize_job`, `persian_kit_normalize_cursor`) on every site of a multisite network. It previously deleted two option names that were never used.
- The release zip no longer includes the WordPress test install, `docs/`, `README.md`, `CHANGELOG.md` or the libraries' dev config files.
- The media library grid filter script now lives in `resources/js/` and is copied by the build, so `npm run build` no longer deletes it.
- Version 1.0.0 in the plugin header, `PERSIAN_KIT_VERSION`, `Stable tag` and `package.json`; `Tested up to: 7.1`. Fixed the abzar link in `readme.txt`.
- Settings saves unslash `$_POST` before sanitizing; the permission message and the admin-bar date are escaped.
- WooCommerce date normalization: the variations handler verifies WooCommerce's nonce before touching `$_POST`, and values are written back slashed so WooCommerce's own `wp_unslash()` doesn't strip them twice.
- The batch normalization panel's script moved from an inline `<script>` into the admin bundle; settings are passed with `wp_add_inline_script()`.
- All user-facing strings are translatable, including the JavaScript ones (`wp_set_script_translations()`), the compatibility guidance cards and the ZWNJ editor button tooltip. Placeholders have translators comments, and the POT now includes JavaScript strings.
- Removed dead code: `ViteHelper`, `BatchMigrator::isComplete()`, `ConflictDetector::detect()`, the container's unused alias/`__get`/`reset`, and the empty deactivation hook. `ModuleInterface` now declares `isEnabled()` and `settingsView()`.

### Development

- GitHub Actions CI on PHP 8.1 and 8.4: unit tests, integration tests against WordPress 7.1, PHPStan level 6 (with `szepeviktor/phpstan-wordpress`), PHPCS with the WordPress security and i18n sniffs, `npm run dist` and Plugin Check on the built plugin.
- Added `composer phpstan` and `composer phpcs`. The code now passes both, fixing the array types, redundant checks and SQL `IN (...)` placeholders they reported.
- PHPUnit configuration migrated to the 10.5 schema; integration tests use `phpunit-integration.xml.dist` for WordPress's PHPUnit 9.6 runner.
- Dev dependencies: wp-scoper 1.4.2, PHPUnit 10.5.65, Mockery 1.6.15, Alpine.js 3.17, Vazirmatn 5.3, Vite 6.4.3.

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
