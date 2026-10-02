=== Persian Kit ===
Contributors: navidkashani
Tags: persian, farsi, jalali, woocommerce, rtl
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0-beta.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A modular Persian (Farsi) language toolkit for WordPress: Jalali dates, digit conversion, character normalization, and editor tooling.

== Description ==

Persian Kit is a modular WordPress plugin for Persian-language sites. It focuses on safe Jalali date display, digit conversion, character normalization, Persian editor tooling, admin typography, and developer-facing PHP utilities.

= Features =

* Jalali date conversion at the display layer
* Jalali archive pages (/1405/07/ lists the posts of Mehr 1405), and a Jalali archive list and calendar in the Archives and Calendar widgets and blocks
* Optional Jalali dates in post permalinks (/1405/07/09/my-post/), with old links redirected
* REST API Jalali companion fields
* Persian digits in content, dates, counts and WooCommerce prices (off until you turn it on)
* Search that finds words typed with either Arabic (ي ك) or Persian (ی ک) letters, and numbers typed with either Persian (۱۴۰۵) or English (1405) digits
* Arabic-to-Persian character normalization on save for posts, comments, categories, tags and menus (off until you turn it on), and for existing posts, with a dry-run count and a confirmation step
* Optional half-spaces (ZWNJ) in compound words when posts are saved
* Persian admin interface
* Vazirmatn-powered admin font support
* ZWNJ editor shortcuts for Classic Editor and Gutenberg
* Persian slugs that keep Persian letters readable (can be turned off)
* PHP validation and formatting helpers for common Iranian data
* WooCommerce Jalali date support for supported screens (HPOS-compatible)
* WooCommerce checkout for Iran: Persian digits in phone numbers and postcodes are saved as English digits, Iranian phone numbers and postcodes are checked, an optional national ID (کد ملی) field, and optional city suggestions for each province
* Persian digits in the WooCommerce cart and checkout blocks' prices
* A Jalali date picker for Contact Form 7 and ACF date fields, WooCommerce's admin date fields and the post date in the classic editor and Quick Edit, which keep saving Gregorian dates
* Contact Form 7 fields that check Iranian mobile numbers, national IDs, postcodes, card numbers and IBANs

= Bundled software =

Persian Kit ships and credits the following third-party components:

* [eram/abzar](https://github.com/eramhq/abzar-php) — MIT-licensed PHP utilities for Persian text and digit handling.
* [eram/daynum](https://github.com/eramhq/daynum) — MIT-licensed PHP Jalali date library.
* [Alpine.js](https://alpinejs.dev) — MIT-licensed JavaScript framework, bundled into the admin script together with its MIT-licensed dependencies `@vue/reactivity` and `@vue/shared`.
* [Vazirmatn](https://github.com/rastikerdar/vazirmatn) — Persian font by Saber Rastikerdar, licensed under the SIL Open Font License 1.1.
* [intl-datepicker](https://github.com/eramhq/intl-datepicker) — MIT-licensed date picker Web Component, bundled into `public/js/datepicker.js`.
* [@internationalized/date](https://github.com/adobe/react-spectrum/tree/main/packages/@internationalized/date) — Adobe's calendar library, licensed under the Apache License 2.0, bundled into `public/js/datepicker.js`.

All bundled components are GPL-compatible. Their license texts ship with the plugin: `packages/eram/*/LICENSE`, `public/fonts/vazirmatn/OFL.txt`, `public/licenses/` and the header of `public/js/admin.js`.

= Source code =

Full source, build instructions, and issue tracker:
[https://github.com/eramhq/persian-kit](https://github.com/eramhq/persian-kit)

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`, or install via the Plugins screen in WordPress.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Visit **Persian Kit** in the admin sidebar to enable the modules you need.

== Frequently Asked Questions ==

= Does Persian Kit support multisite? =

Persian Kit is configured per site. Network activation creates the default settings on every site, and on sites added later; configure each site from its own Persian Kit screen. Uninstalling the plugin removes its options from every site in the network.

= Is Persian Kit compatible with WooCommerce HPOS (custom order tables)? =

Yes. Persian Kit declares HPOS compatibility and the Jalali order date filter works on both the classic orders screen and the HPOS Orders screen.

= Where do translations come from? =

Persian Kit ships a Persian (fa_IR) translation of its settings screen. Once a language pack for your language is available from translate.wordpress.org, WordPress installs it and Persian Kit uses that instead of the bundled file.

= What does Persian Kit change when I activate it? =

Nothing in your saved content. On a new install, dates are shown as Jalali, search matches both Arabic and Persian spellings of ی and ک, Persian slugs and the editor half-space shortcut are on, and the admin uses the Vazirmatn font when its language is Persian. Persian digits and fixing letters on save start off. The settings page shows a short welcome note the first time you open it. Sites upgrading from an earlier version keep their settings.

= How does Persian Kit change slugs? =

With "Use Persian slugs" on under Writing > Persian slugs (the default), new posts and terms with Persian titles get slugs that keep the Persian letters, with a half-space turned into "-". Titles without Persian letters are slugged by WordPress as usual. Existing slugs are never rewritten, and older URLs keep working. Turn the option off to use WordPress's percent-encoded slugs instead. Either way, links in your pages are percent-encoded, as WordPress's own Persian links are; browsers show them with Persian letters.

= What does Persian Kit change in the admin? =

With Jalali dates on, the dashboard's Activity widget, post lists and the admin bar show Jalali dates. The Activity widget is WordPress's own widget re-rendered with Jalali dates. The admin font applies only when the admin language is Persian or right-to-left.

= How do date archives work? =

With Jalali dates on, a date archive whose year is below 1700 is Jalali: `/1405/07/` lists the posts of Mehr 1405, `/1405/` those of the Jalali year and `/1405/07/09/` those of one day, and the title reads "مهر 1405". The Archives and Calendar widgets and blocks list Jalali months and days and link to these pages; turn that off with the "Jalali archives and calendar" option.

Gregorian archives such as `/2025/03/` keep working. Every Gregorian month spans two Jalali months, so their title names both (for example "اسفند 1403 – فروردین 1404").

= Can post permalinks use Jalali dates? =

Yes, if your permalink structure has the date, such as "Day and name". Turn on "Jalali dates in post links" under Display > Jalali dates, and posts link to `/1405/07/09/my-post/` instead of `/2026/10/01/my-post/`. Old Gregorian links redirect to the new ones, and turning the option off again redirects the Jalali links back.

The Jalali links only work while Persian Kit is active. If you deactivate it, they return "not found", so turn the option off first; the Gregorian links then work, and the Jalali ones redirect to them until you deactivate.

= Do I need WooCommerce? =

No. WooCommerce features only activate when WooCommerce is installed and active.

= What does Persian Kit change at the WooCommerce checkout? =

With the WooCommerce module on (Integrations tab), what customers type is fixed before it is saved: Persian and Arabic digits in phone numbers and postcodes become English digits, and Arabic ي and ك in names and addresses become Persian ی and ک. For addresses in Iran, the phone must be a valid mobile or landline number and the postcode must have 10 valid digits. Both work in the classic checkout, the block checkout and My Account. You can also ask for the customer's national ID (optional or required); it is checked, and shown on the order screen and in order emails.

= Does Persian Kit work with Contact Form 7 and ACF? =

Yes. With the Forms module on, Contact Form 7's date fields and ACF's Date Picker and Date Time Picker fields get a Jalali date picker. The dates are still saved and sent as Gregorian dates, so existing entries, emails and theme code keep working; while Jalali dates is on, Contact Form 7 emails and ACF values in templates show the Jalali date. Contact Form 7 also gets [mobile_ir], [national_id], [postcode_ir], [card_ir] and [iban_ir] fields that check what is typed.

= Can customers enter a village? =

Yes. With the city list on, the city field suggests the province's cities, but customers can still type a village or any place that isn't listed.

= Where does the list of cities come from? =

From the Statistical Centre of Iran's country-divisions list for 1403 (amar.org.ir): the 1,454 cities of its 31 provinces.

== Changelog ==

= 1.0.0-beta.4 =
* First WordPress.org release.
* Jalali date archive pages: /1405/07/ lists the posts of Mehr 1405, /1405/ those of the Jalali year and /1405/07/09/ those of one day. Gregorian archive addresses keep working.
* The Archives and Calendar widgets and blocks list Jalali months and days and link to the Jalali archive pages. An option keeps them Gregorian.
* Optional Jalali dates in post permalinks (/1405/07/09/my-post/). Old links redirect to the new ones, and back again if the option is turned off.
* The block editor shows the Jalali date as a "Publish" row of its own, also in the pre-publish and post-publish panels, and "Now" uses the site's time.
* Fixed: the REST API's `date_jalali` and `date_modified_jalali` were empty when a request asked for them with `_fields` but not for `date` or `modified`.
* Persian (fa_IR) admin interface, used until a WordPress.org language pack is available.
* New installs start with Persian digits and fixing letters on save off, and show a short welcome that explains each module. Existing sites keep their settings.
* Search finds words typed with either Arabic (ي ك) or Persian (ی ک) letters, without changing content.
* Persian digits can also apply to Jalali dates, counts and WooCommerce prices, each with its own option.
* Fixing existing posts asks for confirmation, can count first without changing anything, and lets you choose post types.
* Fixed: date pickers allowed 30 Esfand in non-leap years (30 Esfand 1404 was saved as 1 Farvardin 1405).
* Fixed: options added in an update showed as off on the settings screen while running, and saving turned them off.
* Fixed: date archive titles named only one Jalali month, and date links built from Jalali dates led to "not found" pages.
* Fixed: the compatibility guidance never appeared next to Persian WooCommerce.
* Fixed: filters added in a theme's functions.php had no effect.
* Network activation sets up every site, including sites added later.
* Switched core utilities to the `eram/abzar` (0.8) and `eram/daynum` (1.0.0-beta.3) libraries.
* PHP helper functions for developers, all named `persian_kit_*`, including `persian_kit_currency_format`, `persian_kit_currency_convert`, `persian_kit_words_to_number`, `persian_kit_validate_postal_code`, `persian_kit_validate_plate_number`, `persian_kit_validate_bill_id`, `persian_kit_half_space_fix`, `persian_kit_keyboard_fix`, `persian_kit_persian_sort`.
* Persian slugs turn a half-space (ZWNJ) into `-`. Slugs saved by earlier versions keep working, and posts created before activation load again.
* Latin slugs and WooCommerce attribute names (`pa_color`) are left to WordPress, which fixes attributes being renamed to `pa-color`.
* Fixed: feeds (RSS, Atom) showed Jalali dates.
* Fixed: post and comment times were off by the site's timezone offset, so late-evening comments showed the next day.
* Fixed: drafts showed year −1, and the dashboard Activity widget and WooCommerce dates added the timezone offset twice.
* Fixed: `the_date()` printed the date for every post instead of once per day.
* Digit conversion no longer runs in the admin, REST API, feeds, or on text filtered while `wp_mail` runs. Email content rendered before `wp_mail()` is called is still converted.
* WooCommerce: one Jalali month filter per orders screen (HPOS and legacy); declared HPOS compatibility.
* The admin font loads only for Persian or right-to-left admin languages.
* Batch normalization no longer resumes by itself when the settings page opens; WP-CLI and the settings page share progress.
* Uninstalling removes all Persian Kit options, on every site in a network.
* All interface text is translatable, including JavaScript strings.
* Added Jalali media library date filters.

== Upgrade Notice ==

= 1.0.0-beta.4 =
Beta release. Jalali date archives, archive list and calendar, optional Jalali post permalinks, a Persian admin interface, search that finds both spellings of ی and ک, and safer defaults for new installs; existing sites keep their settings.
