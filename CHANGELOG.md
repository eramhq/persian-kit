# Changelog

All notable changes to this project will be documented in this file.

## [Unreleased]

### WooCommerce Analytics

- WooCommerce › Dates › "Jalali dates in WooCommerce Analytics", on by default: Analytics and the WooCommerce home use the Jalali calendar for admins whose language is Persian. "Last month" is 1 to 31 Shahrivar, quarters are Jalali seasons, years start on 1 Farvardin, and "previous year" goes back one Jalali year. Dates in tables, chart axes and tooltips are Jalali; links and saved data stay Gregorian (#29).
- Bars by month, season or year hold whole Jalali months, seasons and years, with WooCommerce's own totals for each, so unique customers and averages stay right. Reports extensions add through WooCommerce's stats endpoints are covered; `persian_kit_analytics_jalali_intervals` opts one out.
- Custom ranges are picked on a Jalali calendar.
- CSV exports, from the browser or emailed, keep the Gregorian date and add a Jalali date column after it.
- The switch from Parsi Date imports its "Jalali dates in Analytics" setting.

### Requirements

- Persian Kit now needs WordPress 6.8 and WooCommerce 9.9 or newer, and is tested on both and on the latest releases. With an older WooCommerce, its WooCommerce features are off and the Integrations tab says why.
- WooCommerce 11.1's block email editor uses Inter by default; Persian emails get the Persian font in its place, as they did in place of Arial.

### WordPress.org review

- The output is escaped where it's printed, and the database queries use `%i` for table names. There is no change users can see.

## [1.0.0-beta.6] - 2026-10-03

### Persian translation

- The Persian (fa_IR) translation reads more plainly and uses one word for each thing: پوسته for theme, محصول for product, صفحه پرداخت for checkout, and WooCommerce's own words for its screens and fields.

### Switching from another plugin

- New on the Tools tab: switch from Parsi Date, Persian WooCommerce or Persian WooCommerce Shipping. Review shows each of the plugin's settings next to Persian Kit's (same, close, not yet or automatic, with the reason), what happens to old links, each kind of data with samples, and what to sort out before deactivating it, all before anything changes. The plugin is deactivated with WordPress's own link; the import then runs in batches, resumes after a reload, and pauses if the plugin is activated again. The report lists what was done, what was not imported and why, and what needs attention, downloads as CSV, and undo puts back what hasn't changed since. It works after the plugin was deleted, from the data it left, and from WP-CLI (`wp persian-kit import`).
- Parsi Date: its settings (6.x and 5.x), widgets and blocks become WordPress's Archives and Calendar, which Persian Kit shows in Jalali, and its ACF date fields and values become ACF's date picker. Theme code calling `parsidate()` or `per_number()` is found, with code to copy into the theme. New helper `persian_kit_jalali_to_gregorian()`.
- Persian WooCommerce Shipping: provinces and cities saved as its ids (or Tapin's) in customers, orders, shipping zones and the store address become WooCommerce's codes and city names, and orders in its statuses move to WooCommerce's with a note, no email and stock unchanged. Its shipping methods have no match, so Review offers to keep it.
- Persian WooCommerce: its settings and old two-letter province codes. Kept only for its payment gateways, with its overlapping options off, it no longer gets compatibility advice.
- The compatibility cards of these plugins link to the switch, and the shipping plugin has its own card.

### Call for price

- WooCommerce › Prices and currency › "Show a text instead of an empty price": a product with an empty price shows «تماس بگیرید» (or the store's text) on its page, and another text in the shop, categories, related products, widgets, product blocks and the Store API. With a phone number or page address, the text on the product page links to it and the shop button reads "Call for price" and goes there in place of "Read more". A variable product with no priced variation no longer reads "out of stock", and products with no price get no sale badge. A price of 0 (free) is left alone. The store's texts can be translated with Polylang or WPML (#11).
- The switch from Persian WooCommerce imports its "call for price" setting and texts, without their HTML; its home page and related products texts become the archive text. Keeping Persian WooCommerce for its gateways now asks to turn its call for price off.

### Shorter checkout

- WooCommerce › Checkout and addresses › "Shorter checkout when nothing needs shipping": when every item in the cart is virtual, the classic and block checkouts ask for the name, country, phone and email, and the national ID when it's on. Company, street, city, province and postcode go; order notes stay. Carts with an item that ships keep the whole address, and addresses saved in My Account don't change (#31).
- The switch from Persian WooCommerce imports its «حذف فیلدهای غیرضروری» (remove extra fields) as this option, as Close: Persian Kit keeps the country and order notes, and also shortens the block checkout. Keeping Persian WooCommerce for its gateways now asks to turn that option off.

### Persian font in emails

- WooCommerce › Emails › "Persian font in emails" (on by default): emails in Persian use Tahoma, Segoe UI, the Persian fonts of iPhones and Android, then Arial, in place of WooCommerce's Helvetica (classic emails) or Arial (block email editor). Only installed fonts, so nothing is downloaded. A font the store picked, a theme's email styles, other plugins' CSS and emails in other languages keep their fonts. On RTL sites, block emails read right to left; WooCommerce made them left to right. `persian_kit_email_font_family` changes the fonts (#34).

### Jalali links

- Old Jalali post links such as `/1403/05/12/my-post/` redirect to the Gregorian link with Jalali dates off, and Jalali archive pages still list their posts. Before, they were "not found" unless Jalali dates were on.

### Persian digits

- Persian digits now reach the browser tab's title (also when Yoast SEO or Rank Math writes it), archive, category and tag titles and descriptions, widget titles, block widgets and Custom HTML widgets, category lists and dropdowns, tag clouds, navigation and page-list blocks, comment excerpts and WooCommerce short descriptions. With the numbers option on, also the post counts in archive lists and tag clouds, and the term-count and query-total blocks.
- Fixed: a title with a dash, a quote, `…` or `&` showed broken characters such as `&#۸۲۱۱;` with Persian digits on. Character references now keep their digits.
- Fixed: Yoast SEO's JSON-LD headline and Rank Math's breadcrumbs got Persian digits. Everything printed in `wp_head` (Open Graph and Twitter titles, JSON-LD) now keeps the digits as typed; only the `<title>` converts. A post saved outside the admin no longer stores a Persian-digit breadcrumb title in Yoast SEO.
- The site name keeps the digits it was typed with in the browser tab's title.
- Gregorian dates, text typed into block theme template parts and product names in the WooCommerce cart keep their digits.

### WooCommerce city list

- The city suggestions are now the plugin's own list instead of the browser's, so they work the same in every browser and screen readers announce them. The list opens as the customer types, or with the down arrow on an empty field, and never on focus or autofill. Arrow keys, Enter, Escape, Tab, a click or a tap pick or close it.
- A city is found however it is typed: spaces and half-spaces, Arabic ي and ك, ئ, گ, hamza and Persian or Arabic digits make no difference, so قائمشهر finds قایم شهر, شاهینشهر finds شاهین شهر and مشکین finds مشگین شهر. A word inside a name matches too (صدرا finds شهر صدرا).
- When an Iranian address is saved in the block or classic checkout or My Account, a city typed another way is saved under its listed name. A place that isn't listed is still saved as typed.
- `persian_kit_woocommerce_cities` now also applies when an address is saved; docs/REFERENCE.md has examples for adding, renaming and removing a city.

### Multilingual sites

- The Jalali calendar on a Persian page links only days, and previous and next months, with posts in the page's language. Before, a day or month with only English posts was linked, and its archive was empty.
- The admin month filters on the posts list and media library list only months with posts in the language chosen in Polylang's or WPML's language filter. "All languages" lists every month, as before.
- An old slug, a cut-off address under a Jalali date, or a Persian slug saved in an older form now leads to the post in the current language when posts in several languages match. A match in another language still redirects.
- Single-language sites run the same queries as before.

## [1.0.0-beta.5] - 2026-10-03

### Integrations

Contact Form 7, ACF and WooCommerce are now integrations: each turns on by itself when its plugin is active.

- WooCommerce has its own tab, shown while WooCommerce is active, with a card for each section: Checkout and addresses (the existing four options), Prices and currency and Dates. Links such as `?tab=woocommerce#prices` open a section. Turning the module off hides the sections.
- New WooCommerce option, Dates > Jalali date picker in the shop admin (on by default): the date pickers on order, product and coupon screens and the month filter on the orders list can now be turned off.
- The Forms module is split into a Contact Form 7 integration (`cf7`) and an ACF integration (`acf`), each with its own card. Existing sites keep what they had: each is on if Forms and its option were both on.
- The Integrations tab has a card for each integration, grouped under headings. Plugins that are not active are listed under "Also works with", each with a link to its WordPress.org page; one set up before says its settings are kept. A plugin that is too old or needs an add-on gets a card that says why. A card is marked New until the tab is opened after its plugin was activated. With no supported plugin active, the tab says so.
- Turning Contact Form 7 off now leaves its Iranian fields (`[national_id]` and the rest) as plain text inputs that accept any text, instead of printing the raw tag on the page. While the switch is off, its card names the forms that use these fields, with links to edit them.
- Contact Form 7's form editor has a button for each Iranian field, which opens CF7's tag generator.
- Advice about another Persian plugin that does the same work also shows on the tab or card it concerns, such as on the WooCommerce tab while Persian WooCommerce is active.

### Settings page

- The page has tabs: Display (Persian digits, Jalali dates, admin font), Writing (Persian ی and ک, half-space key, Persian slugs), WooCommerce, Integrations (Contact Form 7, ACF) and Tools. Save from any tab saves every setting and returns to that tab. The tabs work without JavaScript too.
- Every module and option has one short line instead of a paragraph, in English and Persian. Modules are named for what they do: Digit Conversion is now Persian digits, Date Conversion Jalali dates, Character Normalization Persian ی and ک, ZWNJ Editor Support Half-space key, Utilities Persian slugs and WooCommerce Support WooCommerce. No setting, option key or default changed.
- "Fix existing posts" moved out of the letters module to the Tools tab, as "Fix letters in existing posts".
- The save button stays at the bottom of the screen and says "Unsaved changes" after an edit.
- The compatibility notice on other admin screens uses the same short text as the settings page.
- The Persian translation no longer uses diacritics, such as the ezafe mark.
- New look: a header with the Persian Kit logo, the version and links to what's new, the docs and support, with the tabs below it. Each card has an icon that turns turquoise while the module is on. A footer under a khatam rule shows the version and links to feedback, bug reports and the source, in place of WordPress's "Thank you for creating with WordPress" line and version number on this page. Other admin notices now show below the header.
- The admin menu icon is the new Persian Kit logo, the letter پ in a tile, in place of the translation icon.
- The compatibility guidance on the settings page is one card per plugin, with its details folded under "Review recommended settings". The admin notice about it now shows on the Plugins screen only, since the settings page has the card; its link opens the card.
- Fix letters in existing posts: counts and progress use the admin language's digits (۱٬۲۸۴ in Persian), a bar moves while posts are checked, and the confirmation and the table of counts match the rest of the page.

- Fixed: with Persian slugs, a post, page or category reached by its ID (`/?p=123`, `/?page_id=`, `/?cat=`) redirected to a broken address such as `/بر_ا__-آز_ا_ش_-سا_-1405/` and a 404 on some servers (seen with PHP on macOS). Links to Persian slugs are now percent-encoded, as WordPress's own Persian slugs are; the slug itself keeps its letters. The `persian_kit_utilities` filter turns this off with the `encode_links` feature.
- A cut-off post address under a Jalali date, such as `/1405/07/09/my-po/`, redirects (301) to the post on that date whose slug starts with it, as WordPress does for Gregorian dates. Before, it returned "not found", because WordPress looked for the year 1405.

### WooCommerce: thousand toman and thousand rial

- WooCommerce's currency list has Iranian thousand toman (`IRHT`, هزار تومان) and Iranian thousand rial (`IRHR`, هزار ریال), so a store can write 120 instead of 120,000 toman. These are the codes Persian WooCommerce and Iran's payment gateways use. They stay listed while the WooCommerce module is off.
- A note under WooCommerce › Settings › General › Currency says that changing the currency doesn't convert prices, shipping costs or coupons, to check the payment gateway supports thousand toman, and to set the number of decimals for prices like 12.5.
- Prices in WooCommerce's structured data are in rials (`IRR`) for a store priced in toman, thousand toman or thousand rial: product pages for search engines and order emails for Gmail. Search engines don't accept `IRT`, which isn't an ISO currency code, so toman stores had invalid product markup before. Prices shoppers see don't change. The `persian_kit_schema_rial_prices` filter keeps the store's own currency.

### Yoast SEO and Rank Math

New compatibility integrations for Yoast SEO (`yoast`) and Rank Math (`rank_math`), with a card each under Integrations › Compatibility. They have no switch: each works while its plugin is active.

- Prices in Yoast's and Rank Math's schema, and Rank Math's `product:price` Open Graph tags, are in rials (`IRR`) for a store priced in toman, thousand toman or thousand rial. Rank Math wrote its own product schema with `IRHT` before. The `persian_kit_schema_rial_prices` filter keeps the store's own currency.
- Checked: the dates both plugins give search engines (schema, Open Graph and sitemaps) stay Gregorian with Latin digits, with Jalali dates' global conversion and Persian digits on. Nothing needed changing.
- Fixed: with Yoast SEO (or Rank Math with date archives on), a date archive's title named the Gregorian month, such as "January 1405" on /1405/01/. It names the Jalali month now, as without them.

### Multilingual sites (WPML and Polylang)

On a site with WPML or Polylang, Persian Kit converted everything to Jalali dates and Persian digits whatever the language: an English page read "۱۰ مهر ۱۴۰۵", and saving an Arabic translation changed its ي and ك to ی and ک (#4). Now, on sites with WPML or Polylang and at least one language set up:

- Pages and emails get Jalali dates and Persian digits only in Persian (`fa`, `fa_IR`, `fa_AF`): dates, digits, archive titles, the archive list and calendar, the admin bar clock, WooCommerce dates and email digits, and the ACF date picker. An email follows the language it is sent in, also when WooCommerce Multilingual switches WPML's language for it. Contact Form 7 date fields and mail follow the form's own language, as Contact Form 7 shows the form in it, or else the page's.
- Admin screens follow each admin's own language (Users › Profile › Language).
- The writing tools apply to Persian content only, whoever edits it: the ی/ک and half-space fixes on save (posts, comments by their post, terms), Fix letters in existing posts, Persian slugs and the half-space key.
- Jalali post permalinks follow each post's language. An English post's Jalali address redirects (301) to its Gregorian one.
- WPML and Polylang cards under Integrations › Compatibility, with no switch. WPML's links to its website, as it isn't on WordPress.org.
- New filters: `persian_kit_multilingual` (TranslatePress and other plugins that set the locale per language can opt in), `persian_kit_is_persian_locale`, `persian_kit_current_locale` and `persian_kit_content_locale`. `persian_kit_should_normalize` gets whether the post is Persian.
- Single-language sites are unchanged.
- Polylang is covered by integration tests of its own (`composer test:integration:polylang`, also in CI); WPML, which is commercial, by unit tests.

Known limitations, in the docs: the Jalali calendar, the admin month filters and old-slug lookups count posts in every language; Weglot can't be detected.

### Forminator

Forminator forms get the same Persian support as Contact Form 7 (#8), in a new integration (`forminator`, on by default, Forminator 1.50 or newer):

- Date fields in the Calendar style get the Jalali date picker, in all nine of Forminator's date formats. They still submit and save the Gregorian date in the field's own format, so Forminator's checks, conditions, entries and exports are unchanged. A Jalali date typed without JavaScript is converted. Dropdowns and Number boxes stay Gregorian; the `persian-kit-gregorian` class keeps Forminator's calendar on a field.
- While Jalali dates is on, `{date-1}` and `{all_fields}` in emails, and the Submissions screen for admins who read Persian, show the Jalali date. CSV exports, integrations and webhooks keep the Gregorian date.
- Iranian checks with a class in a field's "Additional CSS Classes": `persian-kit-mobile`, `persian-kit-national-id`, `persian-kit-postcode`, `persian-kit-card` and `persian-kit-iban`, on Text, Phone and Number fields. Invalid values are rejected beside the field; valid ones are saved in their standard form (`09121234567`, also from a Phone field's `+98` country code).
- Persian and Arabic digits become English in Phone, Number and Currency fields, Calendar dates and marked fields, as people type and on the server. Other text keeps its digits.
- Works in forms loaded over AJAX, on later steps of multi-step forms and in repeated Group rows, each row with its own picker and checks. Fields hidden by a condition are not checked.
- Turned off, the fields are plain Forminator fields again, and the card names the forms that use the classes.
- Limitations, in the docs: disabled weekdays and dates, and limits taken from another date field, are checked by Forminator on submit but not greyed out in the Jalali picker; a Number field without a thousands separator is an `<input type="number">`, which some browsers clear when a Persian digit is typed.
- The date picker reads and writes day-month-year formats in any order (`d/m/Y`, `m.d.Y`), and a repeated row copied with its picker starts from the field's default value.

### WooCommerce emails in Persian digits

- New option, Display › Persian digits › WooCommerce emails (off by default): order numbers, prices, quantities and dates in WooCommerce emails, and the order number and date in the subject and heading, use Persian digits. Phone numbers, postcodes, links, coupon codes and the order's structured data for Gmail keep English digits. It covers every order email, also those sent when an admin changes an order's status and those from the block checkout, and only emails in Persian, so an English email on a bilingual store keeps English digits. The `persian_kit_digit_conversion` filter turns it off with `woocommerce_emails`, or for order numbers only with `woocommerce_email_order_number`.
- Changed: emails now follow this option only. Before, emails sent during a classic checkout or by cron got Persian prices and dates from the site-wide filters, and others didn't. With the option off, emails keep English digits.
- `{order_date}` in email subjects and headings, such as the customer note's "…order from {order_date}", is a Jalali date, as in the body.
- WooCommerce's block email editor: the order number, order date and money tags a store inserts, such as the order number in the default "New order: #109" line, follow this option, as the order details below them do. The order date tag is a Jalali date. Links built from a tag keep English digits.
- The order tracking form and the order search under WooCommerce › Orders find an order number typed or pasted with Persian digits ("۱۲۳"). Part of Fix what customers type.

### WooCommerce checkout for Iran

New options under WooCommerce (WooCommerce tab, Checkout and addresses). They apply to the classic checkout, the block checkout and My Account > Addresses, and add to what WooCommerce already has for Iran (provinces, the IRR and IRT currencies).

- Fix what customers type (on by default): Persian and Arabic digits in phone numbers and postcodes become English digits, postcodes lose spaces and dashes (`۱۲۳۴۵-۶۷۸۹۰` is saved as `1234567890`), and Arabic ي/ك in names and addresses become Persian ی/ک. Before, the block checkout rejected a phone number typed in Persian digits as "not valid".
- Check phone numbers and postcodes (on by default): for an address in Iran, the phone must be a valid mobile or landline number and the postcode must have 10 valid digits. Errors name the field. The `persian_kit_woocommerce_validate` filter skips one rule.
- National ID at checkout (off by default; optional or required): asks for the customer's national ID (کد ملی) and checks it. The block checkout gets a WooCommerce additional checkout field (`persian-kit/national-id`); the classic checkout a billing field. Both save the ID in English digits under the order and customer meta key `_wc_other/persian-kit/national-id`, read with `NationalIdField::get($order)`. It is shown on the order screen and in order emails.
- City list for Iran (off by default): for Iranian addresses, the city field suggests the province's cities as the customer types, in the block and classic checkout, the classic cart's shipping calculator and My Account. It stays a text field, so a village or a city that isn't listed can still be typed. Picking another province clears a city of the old one. The list holds the 1,454 cities of the Statistical Centre of Iran's 1403 country-divisions list; the `persian_kit_woocommerce_cities` filter adds places to it.
- Provinces you deliver to (all by default): a store that delivers only to some provinces picks them, and Iranian addresses at checkout (block and classic, billing and shipping), in the classic cart's shipping calculator and in My Account list only those. Another province is refused, by WooCommerce at checkout and in the Store API and by Persian Kit in My Account. With one province, it is selected from the start. The shop admin still lists all 31, and past orders keep their province's name.
- With Persian digits' Shop prices option on, prices in the cart and checkout blocks show Persian digits too. A small script, loaded on those pages only, converts the prices the blocks draw in the browser.

### Forms: a Jalali date picker for Contact Form 7 and ACF

New integrations for Contact Form 7 and ACF (both on by default). Each does nothing unless its plugin is active. The date picker is [intl-datepicker](https://github.com/eramhq/intl-datepicker) with the Persian calendar: Persian month names and digits, right to left, keyboard support and typed dates in Persian digits.

- Contact Form 7 `[date]` fields get the picker and still submit `Y-m-d`, so CF7's checks, emails and stored entries work as before. Without JavaScript a typed Jalali date is converted. While Jalali dates is on, the date in emails is Jalali; `[_raw_name]` keeps the Gregorian date. `[date name gregorian]` keeps CF7's own input.
- New Contact Form 7 fields: `[mobile_ir]`, `[national_id]`, `[postcode_ir]`, `[card_ir]` and `[iban_ir]` check the value and send it in its standard form, whatever digits and separators were typed.
- Persian and Arabic digits in Contact Form 7's phone, number and date fields become English digits before CF7 checks them. Before, a phone number typed in Persian digits was "invalid".
- ACF Date Picker and Date Time Picker fields get the picker on edit screens and in `acf_form()`, in place of ACF's jQuery UI one. Values are stored as before. While Jalali dates is on, `get_field()` returns a Jalali date in the field's return format, except formats code parses such as `Ymd`; the `persian_kit_acf_jalali_value` filter turns it off for a field.
- Developers: `persian_kit_date_field_attributes()` turns any input into a Jalali date field that submits a Gregorian date.
- WooCommerce's admin date fields also accept dates written with slashes or one-digit months, such as `1405/7/10`.

### Admin dates on the date picker

The admin date fields use the Forms module's Jalali date picker, with a calendar, keyboard support and typed dates in Persian digits. They keep saving Gregorian dates.

- WooCommerce: the sale schedule of products and variations, the coupon expiry, the order date and download access expiry. Before, they were text boxes for typing a Jalali date. The two ends of a sale schedule limit each other, Cancel on a schedule clears both, and a picked date marks a variation as changed.
- The post date in the classic editor's publish box and in Quick Edit: one picker, then the hour and minute. The "Published on:" text shows the Jalali date on load and after OK, Cancel or a visibility change. In Quick Edit, Enter and Escape with the calendar open pick a day or close the calendar instead of saving or closing Quick Edit.
- Fixed: Variations > Bulk actions > Set scheduled sale dates saved a Jalali date typed into its prompts, such as `1405/08/01`, in the Gregorian year 1405. It is converted now.
- Fixed: Persian digits typed into the hour and minute of the publish box and Quick Edit made WordPress reject the date or save the hour as 0. They become English digits as you type, as in the WooCommerce order date, whose hour and minute are now text fields because a number field refuses Persian digits.
- Developers: `PersianKitDateField.refresh(input)` re-reads a field's value after a script changed it, `PersianKitDateField.picker(input)` returns its picker, and `data-persian-kit-date-hint="off"` makes the picker compact. A field's value can be a Jalali date. The date picker script also provides `PersianKitCalendar.jalaliToIso()` and `isoToJalali()`.

### Character normalization

- "Fix letters when content is saved" (was "…when posts are saved") also fixes new comments and their author names, the names and descriptions of categories, tags and other terms (WooCommerce product categories and tags included) and menu item titles. Each hook can be turned off with the `persian_kit_char_normalization` filter (`preprocess_comment`, `pre_term_name`, `pre_term_description`).
- Search matches numbers typed either way: a search for `۱۴۰۵` finds posts with `1405`, and the reverse. WooCommerce's product search on the shop page goes through the same filter.
- New option, off by default: "Add half-spaces on save" puts a half-space (ZWNJ) after می and نمی and before suffixes such as ها and ترین in the title, excerpt and content of public posts. HTML tags, code and character references are left alone. It works with or without fixing letters.
- Developers: the save filter moved from a closure in `CharNormalizationModule::boot()` to `PersianKit\Modules\CharNormalization\SaveNormalizer`.

### Admin font

- The admin font can now be Noto Sans Arabic or IBM Plex Sans Arabic as well as Vazirmatn (Display > Admin font). All three ship with the plugin; only the chosen font is downloaded.

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
