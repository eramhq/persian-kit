# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

- Fixed: with Persian slugs, a post, page or category reached by its ID (`/?p=123`, `/?page_id=`, `/?cat=`) redirected to a broken address such as `/بر_ا__-آز_ا_ش_-سا_-1405/` and a 404 on some servers (seen with PHP on macOS). Links to Persian slugs are now percent-encoded, as WordPress's own Persian slugs are; the slug itself keeps its letters. The `persian_kit_utilities` filter turns this off with the `encode_links` feature.
- A cut-off post address under a Jalali date, such as `/1405/07/09/my-po/`, redirects (301) to the post on that date whose slug starts with it, as WordPress does for Gregorian dates. Before, it returned "not found", because WordPress looked for the year 1405.

### Character normalization

- "Fix letters when content is saved" (was "…when posts are saved") also fixes new comments and their author names, the names and descriptions of categories, tags and other terms (WooCommerce product categories and tags included) and menu item titles. Each hook can be turned off with the `persian_kit_char_normalization` filter (`preprocess_comment`, `pre_term_name`, `pre_term_description`).
- Search matches numbers typed either way: a search for `۱۴۰۵` finds posts with `1405`, and the reverse. WooCommerce's product search on the shop page goes through the same filter.
- New option, off by default: "Add half-spaces when posts are saved" puts a half-space (ZWNJ) after می and نمی and before suffixes such as ها and ترین in the title, excerpt and content of public posts. HTML tags, code and character references are left alone. It works with or without fixing letters.
- Developers: the save filter moved from a closure in `CharNormalizationModule::boot()` to `PersianKit\Modules\CharNormalization\SaveNormalizer`.

## [1.0.0-beta.4] - 2026-10-01

First WordPress.org release. Includes everything listed under 1.0.0-beta.3 and 1.0.0-beta.2, plus the changes below.

### Block editor dates

- The block editor's Jalali date is a "Publish" row of its own in the post sidebar, built on the editor's plugin API (`PluginPostStatusInfo`) instead of rewriting core's date picker in the page. Core's Gregorian row is hidden with one CSS rule; if WordPress renames it, both rows show and both work.
- The row's label updates as you type. Before, it showed the previous date while the date popover was open.
- The pre-publish and post-publish panels show the Jalali date. Core's Gregorian "Publish:" panel in the pre-publish checks is hidden; it has no class of its own, so it is found by its title.
- Dates are read and written as the editor's site-local text, and "Now" is the site's time. Before, both went through the browser's timezone, so an editor in another timezone got a wrong "Now".
- The date script loads on the post editor only, not the site or widget editors.

### Jalali archives

- Jalali date archive pages: `/1405/07/` lists exactly the posts of Mehr 1405, `/1405/` those of the Jalali year and `/1405/07/09/` those of one day (`?m=140507` with plain permalinks). A year below 1700 is read as Jalali; Gregorian archive URLs work as before. The title of a Jalali archive names its period ("مهر 1405"). Day pages are checked against the Jalali calendar, so 31 Shahrivar (`/1404/06/31/`) loads and 31 Mehr redirects to the month, as an invalid Gregorian day does.
- The archive list is Jalali: `wp_get_archives()`, and with it the Archives widget and block (list and dropdown), lists Jalali months ("مهر 1405"), years or days with their post counts, linked to the Jalali archive pages. `limit`, `order`, `format`, `show_post_count` and `post_type` work as before, and conditions other plugins add through `getarchives_where` are kept. A new option under Date Conversion, "Show the archive list and calendar in Jalali", is on by default, also for existing sites; the `persian_kit_jalali_archives` filter turns it off in code.
- The calendar is Jalali: `get_calendar()`, and with it the Calendar widget and block, shows a Jalali month ("مهر 1405") with its 29, 30 or 31 days, starting on the site's first day of the week. Days with posts link to the Jalali day archive, and the previous and next links go to the nearest Jalali months with posts. The markup is core's, so theme and block styles apply. The same option and filter turn it off; with it off, WordPress's calendar on a Jalali archive shows the Gregorian month that overlaps it most (WordPress 6.8+).
- Changed: date links built from Jalali parts, such as `get_month_link(get_the_time('Y'), get_the_time('m'))` in a theme, now lead to the Jalali archive (`/1403/12/`) instead of redirecting to a Gregorian month (`/2025/03/`).
- Jalali dates in post permalinks: a new option under Date Conversion, "Use Jalali dates in post permalinks", off by default, links posts to `/1405/07/09/my-post/` instead of `/2026/10/01/my-post/` when the permalink structure has the date. The post's pages, feed, embed, comment and attachment pages follow. Addresses in the other calendar redirect (301) to the current one, keeping the rest of the path and the query string, so old links keep working whichever way the option is switched; old slugs and dates under a Jalali date redirect too. `url_to_postid()` resolves Jalali addresses, also in the admin. The `persian_kit_jalali_permalinks` filter turns the Jalali links off in code. Jalali addresses only work while Persian Kit is active.

- The compatibility note for WP Jalali says Persian Kit can also serve Jalali archive pages and put Jalali dates in post permalinks.

### Robustness

- Services that only work in the admin (the settings page and its assets, the posts, media and WooCommerce order month filters, the media date formatter, the admin date pickers and the WooCommerce date fields) are no longer created on front-end, REST, cron or WP-CLI requests. They still load for admin-ajax and admin-post.
- The settings screen saves through the WordPress Settings API (`options.php`) instead of a custom `admin-post.php` handler, and the `persian_kit_settings` option is sanitized on every write, from any code: module values are merged over the stored ones and sanitized by their module, a module left out keeps its values, and unknown keys are dropped. Options of the WooCommerce Support and ZWNJ modules are stored as booleans instead of `'1'`/`'0'`. The form's fields are named `persian_kit_settings[module][key]` (were `modules[module][key]`), and the `admin_post_persian_kit_save` action is gone.
- The REST `date_jalali` and `date_modified_jalali` fields were `null` when `_fields` left out `date` or `modified`, as in `?_fields=id,date_jalali`. They are now read from the post itself.
- `wp persian-kit normalize` is available whether or not the Character Normalization module is on. It is registered on `cli_init`.
- Developers: `ServiceContainer::get()` throws `PersianKit\Container\ServiceNotFoundException` for an id that was never registered, instead of returning `null`. The list of modules lives in one place, `PersianKit\Core\ModuleRegistry::MODULES`, and the `'modules'` container entry is gone.

## [1.0.0-beta.3] - Not released separately

These changes ship in 1.0.0-beta.4. Includes everything listed under 1.0.0-beta.2, plus the fixes from the pre-release review below.

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

These changes ship in 1.0.0-beta.4.

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
