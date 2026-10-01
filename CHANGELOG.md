# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### Block editor dates

- The block editor's Jalali date is a "Publish" row of its own in the post sidebar, built on the editor's plugin API (`PluginPostStatusInfo`) instead of rewriting core's date picker in the page. Core's Gregorian row is hidden with one CSS rule; if WordPress renames it, both rows show and both work.
- The row's label updates as you type. Before, it showed the previous date while the date popover was open.
- The pre-publish and post-publish panels show the Jalali date.
- Dates are read and written as the editor's site-local text, and "Now" is the site's time. Before, both went through the browser's timezone, so an editor in another timezone got a wrong "Now".
- The date script loads on the post editor only, not the site or widget editors.

## [1.0.0-beta.3] - Unreleased

First WordPress.org release. Includes everything listed under 1.0.0-beta.2, plus the fixes from the pre-release review below.

### What users need

- Persian admin interface: a Persian (fa_IR) translation ships in `languages/`, including the settings screen's JavaScript strings. It is loaded only while no WordPress.org language pack is installed for the locale, so translate.wordpress.org takes over once a pack exists. `npm run build:i18n` compiles the `.po` file.
- Safer first run. New installs start with digit conversion and fixing letters on save off; dates, search, the editor half-space, the admin font (Persian admins only), WooCommerce dates and Persian slugs are on. The settings screen shows a welcome notice that explains each module until it is dismissed. Existing sites keep their settings: the upgrade to settings version 2 writes their old behaviour out explicitly.
- Digit conversion has options for Jalali dates (through `persian_kit_date_display`), counts (`number_format_i18n`) and WooCommerce prices (`formatted_woocommerce_price`). Text in `<kbd>` and `<samp>` keeps its digits, like code.
- Search matches both spellings: a `posts_search` filter matches each term as typed, with Persian ی/ک and with Arabic ي/ك, so posts saved before normalization are found. Search terms are no longer rewritten, and their digits are no longer turned into Latin digits (that broke matching on `utf8mb4_general_ci` databases). Fixing letters on save is now a separate option, "Fix letters when posts are saved", and the `persian_kit_char_normalization` context for search is `posts_search` instead of `pre_get_posts`.
- A Settings link on the Plugins screen and a translation icon for the menu.
- Plain-language module descriptions. "Global date conversion (wp_date hook)" is now "Convert all dates (advanced)", with its side effects listed. The Utilities description says the switch controls slugs only; the helper functions always load.
- Developers: `persian_kit_loaded` and the `function_exists()` guard are documented. The duplicate `persian_kit_known_conflicts` filter is removed; use `persian_kit_conflict_policies`.

### Correctness fixes

- Date pickers (classic editor, Quick Edit, block editor, WooCommerce) used a different leap-year rule from the date converter, and allowed 30 Esfand in every year. 30 Esfand 1404, which does not exist, was saved as 1 Farvardin 1405. Leap years now come from the converter itself, and a JavaScript test (`npm run test:js`) runs in CI.
- The settings screen showed a module or option added in an update as off while it was running, and saving the screen then turned it off. Stored settings are now always read on top of each module's defaults. Settings are saved in one write instead of one per module.
- Settings have a schema version (`persian_kit_db_version`) and an upgrade routine on `plugins_loaded`. Network activation creates the default settings on every site, and on sites created later (`wp_initialize_site`).
- The compatibility card never appeared for Persian WooCommerce: its main file is `persian-woocommerce/woocommerce-persian.php`. The Persian WooCommerce and WP-Parsidate cards now recommend turning off the WooCommerce Support module.
- Date archives: `/2025/03/` was titled with the first post's Jalali month, although a Gregorian month spans two. The archive title and `<title>` now name the whole period (`اسفند 1403 – فروردین 1404`; a year archive shows `1403 – 1404`). Date links built from the Jalali parts of a post date (`get_month_link(get_the_time('Y'), get_the_time('m'))`) are mapped back to the Gregorian archive instead of a 404.
- Modules now boot on `after_setup_theme` (priority 20) instead of `plugins_loaded`, so filters such as `persian_kit_digit_conversion` added in a theme's `functions.php` take effect. `persian_kit_loaded` still fires on `plugins_loaded`, before the modules boot.
- Batch normalization:
  - The settings panel asks for confirmation, with a backup reminder, before it changes posts.
  - "Count posts to fix" does a dry run and shows, per post type (by its label), the posts the saved settings would change.
  - A post type picker chooses what to fix. A resumed job keeps the post types it started with.
  - The fix is disabled while the settings form has unsaved changes.
  - The count leaves Teh Marbuta (ة) out unless that option is on.
  - Opening the settings page no longer scans every post.
  - The Run button no longer stays hidden after a completed job.
  - `wp persian-kit normalize --dry-run` uses the same exact count.
- The module toggles have accessible names and descriptions, and the batch status is announced to screen readers (`aria-live`).
- The REST `date_jalali` and `date_modified_jalali` schema no longer call the value ISO format; it is a Jalali date laid out like ISO 8601.
- The docs now describe the `wp_mail` exclusion accurately: only text filtered while `wp_mail` runs is skipped.

## [1.0.0-beta.2] - Not released separately

These changes ship in 1.0.0-beta.3.

- Prepared the plugin for WordPress.org submission: added `readme.txt`, `uninstall.php`, and `Tested up to:` plugin header.
- Bumped `Requires at least:` to WordPress 6.5 (for `wp_is_serving_rest_request()`).
- Declared WooCommerce HPOS compatibility via `before_woocommerce_init`.
- Removed `load_plugin_textdomain()` call; translations are auto-loaded by WordPress core for hosted plugins. (The bundled Persian translation added later loads only when no language pack is installed.)
- Replaced the inline `<style>` in the WooCommerce orders Jalali month filter with a conditionally enqueued stylesheet.
- Renamed the admin bundle from `admin.min.js` to `admin.js` and disabled minification so the source-equivalent ships in the dist.
- Removed the non-standard `Plugin Prefix:` header.
- Tightened `.distignore` against AI-tooling directories and OS metadata files.

### Breaking: helper functions renamed

- Every `pk_*` helper is now `persian_kit_*` (`pk_date()` → `persian_kit_date()`), matching the plugin's prefix as WordPress.org requires. The old names are removed.

### Library upgrade: eram/abzar 0.8, eram/daynum 1.0.0-beta.3

- Upgraded `eram/abzar` from 0.5 to `^0.8@beta` and `eram/daynum` to 1.0.0-beta.3.
- Persian slugs: a ZWNJ in a title now becomes `-` (`می‌خواهم` → `می-خواهم`), and Persian punctuation, kashida and tashkeel are dropped. Slugs saved by earlier versions with a ZWNJ still resolve.
- Persian slug handling now applies only to titles that contain Persian/Arabic letters. Other titles go through WordPress core again, which fixes WooCommerce attribute taxonomies (`pa_color` was turned into `pa-color`), percent-encoded slugs and accented Latin letters (`Café` → `cafe`).
- Posts created before the plugin was activated (stored with WordPress's percent-encoded slugs) load again. A request whose slug only differs in ZWNJ vs `-` is redirected (301) to the post.
- Digit conversion and character normalization leave content unchanged instead of failing when PCRE cannot process very large or malformed HTML. The batch normalizer skips such posts and continues.
- `persian_kit_ordinal_short()` takes `bool $persianDigits = true` as its second argument. The old `'persian'` / `'english'` strings still work.
- Behaviour changes in the `persian_kit_*` helpers that come from abzar:
  - `persian_kit_keyboard_fix()` maps upper-case Latin letters through the Persian Shift layer (`H` → `آ`) instead of lower-casing them.
  - Validators report a warning for unknown national-ID city codes, IBAN bank codes and phone area codes; `isValid()` stays `true`. Use `isStrictlyValid()` to reject them.
  - Licence-plate province and plate-type data were corrected.
  - `persian_kit_ordinal_word(30)` returns `سی‌ام` (was `سی اُم`).
  - `persian_kit_words_to_number()` returns `null` for text that is not a number (`دو سه`) and for values past `PHP_INT_MAX`.

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
- Version 1.0.0-beta.2 in the plugin header, `PERSIAN_KIT_VERSION`, `Stable tag` and `package.json`; `Tested up to: 7.1`. Fixed the abzar link in `readme.txt`.
- Settings saves unslash `$_POST` before sanitizing; the permission message and the admin-bar date are escaped.
- WooCommerce date normalization: the variations handler verifies WooCommerce's nonce before touching `$_POST`, and values are written back slashed so WooCommerce's own `wp_unslash()` doesn't strip them twice.
- The batch normalization panel's script moved from an inline `<script>` into the admin bundle; settings are passed with `wp_add_inline_script()`.
- All user-facing strings are translatable, including the JavaScript ones (`wp_set_script_translations()`), the compatibility guidance cards and the ZWNJ editor button tooltip. Placeholders have translators comments, and the POT now includes JavaScript strings.
- Plugin Check reports no errors. The bundled libraries are marked as third-party code at build time; the plugin's direct database queries and template variables carry the reason they are needed.
- Review fixes before WordPress.org submission:
  - The bundled libraries' licence files now ship with them: `packages/eram/*/LICENSE`, the Vazirmatn `OFL.txt`, and Alpine.js's MIT notice in `admin.js`.
  - The Persian slug filter runs after WordPress's own (priority 11) instead of replacing it, and has its own "Persian slugs" option in the Utilities module.
  - The compatibility notice disappears once the overlapping modules are turned off. The guidance cards now report a disabled module as off (before, any module showed "Currently on").
  - Strings borrowed from WordPress core use the plugin's text domain; the media library date uses the site's date format.
  - Classic-editor date labels are translatable; the block editor uses WordPress's own "Date", "Time" and "Immediately" strings.
  - Block date output is escaped, and the classic-editor date fields build their HTML from numbers only.
  - Templates read their values from `$args` instead of `extract()`, and view names are validated.
  - Alpine.js is no longer exposed as `window.Alpine`, and the admin font CSS variable is `--persian-kit-admin-font`.
- Removed dead code: `ViteHelper`, `BatchMigrator::isComplete()`, `ConflictDetector::detect()`, the container's unused alias/`__get`/`reset`, and the empty deactivation hook. `ModuleInterface` now declares `isEnabled()` and `settingsView()`.

### Development

- GitHub Actions CI on PHP 8.1 and 8.4: unit tests, integration tests against WordPress 7.1, PHPStan level 6 (with `szepeviktor/phpstan-wordpress`), PHPCS with the WordPress security and i18n sniffs, `npm run dist` and Plugin Check on the built plugin.
- Added `composer phpstan` and `composer phpcs`. The code now passes both, fixing the array types, redundant checks and SQL `IN (...)` placeholders they reported.
- PHPUnit configuration migrated to the 10.5 schema; integration tests use `phpunit-integration.xml.dist` for WordPress's PHPUnit 9.6 runner.
- Dev dependencies: wp-scoper 1.4.2, PHPUnit 10.5.65, Mockery 1.6.15, Alpine.js 3.17, Vazirmatn 5.3, Vite 6.4.3.

### Documentation

- `docs/REFERENCE.md` and `docs/UTILITIES.md` describe abzar's `ValidationResult`: `detail()` returns an object with read-only properties (`->details()` no longer exists), plus `warnings()`, `errorCodes()` and `isStrictlyValid()`.
- Documented the nine helpers added in 1.0.0-beta.1, the `persian_kit_utilities` filter and the Persian slug behavior. Formatters are documented as throwing `FormatException` (a `RuntimeException`).
- `README.md` lists the 1.0.0-beta.2 release and WordPress 6.5+; `docs/DEVELOPMENT.md` covers PHPStan, PHPCS, CI and the release checklist.

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
