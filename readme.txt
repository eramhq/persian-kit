=== Persian Kit ===
Contributors: navidkashani
Tags: persian, farsi, jalali, woocommerce, rtl
Requires at least: 6.5
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: X.Y.Z
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
* Persian slug generation
* PHP validation and formatting helpers for common Iranian data
* WooCommerce Jalali date support for supported screens (HPOS-compatible)

= Bundled software =

Persian Kit ships and credits the following third-party components:

* [eram/abzar](https://github.com/eramhq/abzar) — MIT-licensed PHP utilities for Persian text and digit handling.
* [eram/daynum](https://github.com/eramhq/daynum) — MIT-licensed PHP Jalali date library.
* [Alpine.js](https://alpinejs.dev) — MIT-licensed JavaScript framework, bundled into the admin script.
* [Vazirmatn](https://github.com/rastikerdar/vazirmatn) — Persian font by Saber Rastikerdar, licensed under the SIL Open Font License 1.1.

All bundled components are GPL-compatible.

= Source code =

Full source, build instructions, and issue tracker:
[https://github.com/eramhq/persian-kit](https://github.com/eramhq/persian-kit)

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install via the Plugins screen in WordPress.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Visit **Persian Kit** in the admin sidebar to enable the modules you need.

== Frequently Asked Questions ==

= Does Persian Kit support multisite? =

Persian Kit is built for single-site installations in this release. Network activation works at the plugin level, but per-site defaults and uninstall cleanup are not propagated to subsites. Activate and configure the plugin per site.

= Is Persian Kit compatible with WooCommerce HPOS (custom order tables)? =

Yes. Persian Kit declares HPOS compatibility and the Jalali order date filter works on both the classic orders screen and the HPOS Orders screen.

= Where do translations come from? =

Translations are loaded automatically by WordPress from the WordPress.org translation system. You do not need to manually load a language pack.

= Do I need WooCommerce? =

No. WooCommerce features only activate when WooCommerce is installed and active.

== Changelog ==

= X.Y.Z =
* First WordPress.org release.
* Switched core utilities to the `eram/abzar` and `eram/daynum` libraries.
* Added `pk_*` helpers: `pk_currency_format`, `pk_currency_convert`, `pk_words_to_number`, `pk_validate_postal_code`, `pk_validate_plate_number`, `pk_validate_bill_id`, `pk_half_space_fix`, `pk_keyboard_fix`, `pk_persian_sort`.
* Declared WooCommerce HPOS compatibility.
* Hardened WooCommerce classic admin date handling.
* Added Jalali media library date filters.

== Upgrade Notice ==

= X.Y.Z =
First WordPress.org release.
