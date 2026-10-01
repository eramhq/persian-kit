=== Persian Kit ===
Contributors: navidkashani
Tags: persian, farsi, jalali, woocommerce, rtl
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0-beta.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A modular Persian (Farsi) language toolkit for WordPress: Jalali dates, digit conversion, character normalization, and editor tooling.

== Description ==

Persian Kit is a modular WordPress plugin for Persian-language sites. It focuses on safe Jalali date display, digit conversion, character normalization, Persian editor tooling, admin typography, and developer-facing PHP utilities.

= Features =

* Jalali date conversion at the display layer
* REST API Jalali companion fields
* Persian digit conversion for content areas
* Arabic-to-Persian character normalization (live and batch)
* Vazirmatn-powered admin font support
* ZWNJ editor shortcuts for Classic Editor and Gutenberg
* Persian slugs that keep Persian letters readable (can be turned off)
* PHP validation and formatting helpers for common Iranian data
* WooCommerce Jalali date support for supported screens (HPOS-compatible)

= Bundled software =

Persian Kit ships and credits the following third-party components:

* [eram/abzar](https://github.com/eramhq/abzar-php) — MIT-licensed PHP utilities for Persian text and digit handling.
* [eram/daynum](https://github.com/eramhq/daynum) — MIT-licensed PHP Jalali date library.
* [Alpine.js](https://alpinejs.dev) — MIT-licensed JavaScript framework, bundled into the admin script together with its MIT-licensed dependencies `@vue/reactivity` and `@vue/shared`.
* [Vazirmatn](https://github.com/rastikerdar/vazirmatn) — Persian font by Saber Rastikerdar, licensed under the SIL Open Font License 1.1.

All bundled components are GPL-compatible. Their license texts ship with the plugin: `packages/eram/*/LICENSE`, `public/fonts/vazirmatn/OFL.txt` and the header of `public/js/admin.js`.

= Source code =

Full source, build instructions, and issue tracker:
[https://github.com/eramhq/persian-kit](https://github.com/eramhq/persian-kit)

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install via the Plugins screen in WordPress.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Visit **Persian Kit** in the admin sidebar to enable the modules you need.

== Frequently Asked Questions ==

= Does Persian Kit support multisite? =

Persian Kit is configured per site. Network activation works at the plugin level, but default settings are created only on the site where the plugin is activated; configure each site from its own Persian Kit screen. Uninstalling the plugin removes its options from every site in the network.

= Is Persian Kit compatible with WooCommerce HPOS (custom order tables)? =

Yes. Persian Kit declares HPOS compatibility and the Jalali order date filter works on both the classic orders screen and the HPOS Orders screen.

= Where do translations come from? =

Translations are loaded automatically by WordPress from the WordPress.org translation system. You do not need to manually load a language pack.

= How does Persian Kit change slugs? =

With the Utilities module's "Persian slugs" option on (the default), new posts and terms with Persian titles get slugs that keep the Persian letters, with a half-space turned into "-". Titles without Persian letters are slugged by WordPress as usual. Existing slugs are never rewritten, and older URLs keep working. Turn the option off to use WordPress's percent-encoded slugs instead.

= What does Persian Kit change in the admin? =

With Date Conversion on, the dashboard's Activity widget, post lists and the admin bar show Jalali dates. The Activity widget is WordPress's own widget re-rendered with Jalali dates. The admin font applies only when the admin language is Persian or right-to-left.

= Do I need WooCommerce? =

No. WooCommerce features only activate when WooCommerce is installed and active.

== Changelog ==

= 1.0.0-beta.2 =
* First WordPress.org release.
* Switched core utilities to the `eram/abzar` (0.8) and `eram/daynum` (1.0.0-beta.3) libraries.
* PHP helper functions for developers, all named `persian_kit_*`, including `persian_kit_currency_format`, `persian_kit_currency_convert`, `persian_kit_words_to_number`, `persian_kit_validate_postal_code`, `persian_kit_validate_plate_number`, `persian_kit_validate_bill_id`, `persian_kit_half_space_fix`, `persian_kit_keyboard_fix`, `persian_kit_persian_sort`.
* Persian slugs turn a half-space (ZWNJ) into `-`. Slugs saved by earlier versions keep working, and posts created before activation load again.
* Latin slugs and WooCommerce attribute names (`pa_color`) are left to WordPress, which fixes attributes being renamed to `pa-color`.
* Fixed: feeds (RSS, Atom) showed Jalali dates.
* Fixed: post and comment times were off by the site's timezone offset, so late-evening comments showed the next day.
* Fixed: drafts showed year −1, and the dashboard Activity widget and WooCommerce dates added the timezone offset twice.
* Fixed: `the_date()` printed the date for every post instead of once per day.
* Digit conversion no longer runs in the admin, REST API, feeds or outgoing email.
* WooCommerce: one Jalali month filter per orders screen (HPOS and legacy); declared HPOS compatibility.
* The admin font loads only for Persian or right-to-left admin languages.
* Batch normalization no longer resumes by itself when the settings page opens; WP-CLI and the settings page share progress.
* Uninstalling removes all Persian Kit options, on every site in a network.
* All interface text is translatable, including JavaScript strings.
* Added Jalali media library date filters.

== Upgrade Notice ==

= 1.0.0-beta.2 =
Beta release. Fixes Jalali dates in feeds and timezone offsets. New Persian slugs use "-" instead of a half-space; existing URLs keep working.
