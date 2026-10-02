# Reference

This document is the developer-facing API reference for Persian Kit. It covers the callable PHP functions, their return contracts and exceptions, and the plugin's hooks.

## Runtime Model

- Public helpers are defined in [src/functions.php](../src/functions.php).
- Most helpers are thin wrappers around the bundled [eram/abzar](https://github.com/eramhq/abzar-php) (text, numbers, validation) and [eram/daynum](https://github.com/eramhq/daynum) (Jalali calendar) libraries.
- Both libraries are bundled under the `PersianKit\Dependencies\` namespace, so they never clash with another plugin's copy. Their classes are named below with that prefix.
- Validation helpers do not throw for invalid user input. They return a `ValidationResult`.
- Formatting helpers throw abzar's `FormatException` when the input can't be interpreted. It extends `\RuntimeException`, so `catch (\RuntimeException $e)` is enough.

## ValidationResult Contract

Validation helpers return `PersianKit\Dependencies\Eram\Abzar\Validation\ValidationResult`.

Methods:

- `isValid(): bool`: the input is well-formed and passes its checksum.
- `isStrictlyValid(): bool`: valid and without warnings, so every optional lookup (city, bank, operator, area code, plate letter) resolved.
- `errors(): list<string>`: Persian error messages; empty on success.
- `errorCodes(): list<ErrorCode>`: machine-readable error codes.
- `warnings(): list<string>` / `warningCodes(): list<ErrorCode>`: issues that don't make the input invalid, such as an unknown bank code.
- `detail(): ?ValidationDetail`: parsed details as an object with read-only properties, or `null`.
- `jsonSerialize(): array`: the whole result as an array, with snake_case detail keys.

Typical usage:

```php
$result = persian_kit_validate_phone('09123456789');

if (!$result->isValid()) {
    wp_send_json_error([
        'errors' => $result->errors(),
    ], 422);
}

$e164 = $result->detail()?->normalizedE164;
```

Behavior notes:

- `detail()` returns an object, not an array. Read its properties (`$detail->bank`) or call `jsonSerialize()` when you need an array.
- An unknown lookup does not make input invalid. A national ID with an unlisted city prefix, an IBAN with an unlisted bank code, or a landline with an unlisted area code is valid, carries a warning, and leaves the lookup property `null`. Use `isStrictlyValid()` to reject these.
- Every validator strips the characters that come along when IDs are pasted from phones and chat apps: NBSP, ZWNJ/ZWJ, bidi marks, BOM, soft hyphens and Unicode dashes.

## Public PHP API

### Date Helpers

#### `persian_kit_date(string $format, int|string $timestamp = '', ?DateTimeZone $timezone = null): string`

Formats a timestamp as a Jalali date using PHP `date()` format tokens.

Notes:

- Returns English digits. Digit conversion is a separate concern.
- Accepts Unix timestamps and `strtotime()`-compatible strings. Strings without a timezone are read as UTC.
- Uses the site timezone unless `$timezone` is given.
- The output passes through the `persian_kit_date_display` filter.

```php
echo persian_kit_date('Y/m/d', time());
```

#### `persian_kit_gregorian_date(string $format, int|string $timestamp = '', ?DateTimeZone $timezone = null): string`

Formats a timestamp as a Gregorian date, bypassing Jalali conversion even when global conversion of `wp_date()` is on.

Use it for machine-oriented or interoperable output.

#### `persian_kit_date_field_attributes(array $options = []): string`

Turns an `<input>` into a Jalali date field: returns its attributes, escaped, and loads the date picker on the page. The field still submits a Gregorian date, so whatever reads the form needs no change. Without JavaScript the input stays as it is.

Options:

- `format`: what the field submits, `Y-m-d` (default), `Ymd`, or `Y-m-d H:i:s`, which adds a time input.
- `type`: `date` (default), `range`, `multiple`, `month` or `year`. Types other than `date` submit the picker's own value: `2026-10-02/2026-10-05` for a range, comma-separated dates for `multiple`.
- `min`, `max`: Gregorian or Jalali dates (`2026-01-01`, `۱۴۰۵/۰۱/۰۱`).
- `disable_past`, `disable_future`: booleans.
- `locale`: a BCP 47 tag; the page's language by default.

Unknown options and values are ignored.

```php
<input type="text" name="birthday" <?php echo persian_kit_date_field_attributes(['max' => '2010-12-31']); ?>>
```

The picker is [intl-datepicker](https://github.com/eramhq/intl-datepicker) with the Persian calendar. Style it with its `--idp-*` CSS properties on `intl-datepicker.persian-kit-date-picker`.

The input's own `required`, `disabled`, `readonly`, `placeholder`, `aria-label`, `min` and `max` carry over to the picker. A value already in the input can be Gregorian, or Jalali in the field's format (a year from 1200 to 1600, Persian, Arabic or English digits, `-` or `/`): the picker shows it, and the input keeps it until a date is picked. Add `data-persian-kit-date-hint="off"` to a field in a cramped row: the picker is as wide as a date and its typing hint is read by screen readers only.

Scripts reach a field through `window.PersianKitDateField`:

- `upgrade(input)`, `upgradeAll(root)`: upgrade fields now. Fields added to the page later are upgraded on their own.
- `refresh(input)`: after a script changed the input's value (jQuery's `.val()` fires no events), shows it in the picker. Fires no events.
- `picker(input)`: the field's `<intl-datepicker>`, for example to set `min` and `max`; `null` before the field is upgraded.

A picked date fires `input` and `change` on the input. `window.PersianKitCalendar.jalaliToIso(year, month, day)` and `isoToJalali('2026-10-02')` convert dates with the picker's calendar; they return `null` for a day that does not exist.

The plugin's own admin date fields use the picker too: WooCommerce's sale schedules, coupon expiry, order date and download access expiry, and the post date in the classic editor and Quick Edit. The block editor's date row is separate.

### Digit Conversion

#### `persian_kit_to_persian_digits(string $text): string`

Converts ASCII and Arabic-Indic digits to Persian digits.

```php
persian_kit_to_persian_digits('Order 123'); // Order ۱۲۳
```

#### `persian_kit_to_english_digits(string $text): string`

Converts Persian and Arabic-Indic digits to ASCII digits. Use it before numeric validation or storage.

#### `persian_kit_to_arabic_digits(string $text): string`

Converts ASCII and Persian digits to Arabic-Indic digits.

### Text Helpers

#### `persian_kit_normalize_persian(string $text): string`

Normalizes Arabic characters for Persian use:

- Arabic Yeh → Persian Yeh
- Arabic Kaf → Persian Kaf
- Arabic-Indic digits → Persian digits

It uses the default normalizer and does not read module settings such as `teh_marbuta`.

#### `persian_kit_slug(string $text): string`

Generates a URL-safe slug that keeps Persian letters.

Behavior:

- Normalizes Arabic Yeh/Kaf and converts digits to English
- Lowercases Latin letters
- Turns whitespace, underscores and ZWNJ into `-`
- Drops Persian punctuation (`،` `؛` `؟` `٪` `٫` `٬` `۔`), kashida and tashkeel
- Removes other unsupported characters and collapses repeated hyphens

```php
persian_kit_slug('نمونه نوشته ۱۴۰۵');     // نمونه-نوشته-1405
persian_kit_slug('می‌خواهم بنویسم'); // می-خواهم-بنویسم
```

See [Persian slugs](#persian-slugs) for how the Persian slugs module applies this to post slugs.

#### `persian_kit_half_space_fix(string $text): string`

Inserts a ZWNJ (half-space) where common Persian affixes are written with a space or joined, for example `می‌`, `نمی‌`, `ها`, `تر` and `ترین`. Best effort; it only touches known affix patterns.

```php
persian_kit_half_space_fix('می خواهم کتاب ها را'); // می‌خواهم کتاب‌ها را
```

#### `persian_kit_keyboard_fix(string $text): string`

Fixes text typed with the wrong keyboard layout. Latin input is mapped to the Persian (ISIRI 9147) layout, and Persian input is mapped back to Latin.

Upper-case Latin letters map through the Persian Shift layer (`H` → `آ`, `C` → `ژ`, `B` → ZWNJ).

```php
persian_kit_keyboard_fix('sghl'); // سلام
persian_kit_keyboard_fix('سلام'); // sghl
```

#### `persian_kit_persian_sort(array $items, ?callable $key = null): array`

Returns a new array sorted in Persian alphabetical order. It does not sort by reference.

- Pass `$key` to sort non-string items: `persian_kit_persian_sort($users, fn ($u) => $u->display_name)`.
- Correct Persian collation needs the `intl` PHP extension. Without it the helper falls back to PHP's byte-order `sort()`/`strcmp()`, which misplaces letters such as `پ`, `چ`, `ژ` and `گ`.

### Validation Helpers

All validators accept Persian and Arabic-Indic digits and ignore surrounding whitespace.

#### `persian_kit_validate_national_id(string $id): ValidationResult`

Validates an Iranian national ID (کد ملی).

- Left-pads 8–9 digit input to 10 digits.
- Rejects repeated-digit patterns and failed checksums.
- An unlisted city prefix is valid with a warning.

Detail properties (`NationalIdDetails`): `value`, `cityCode`, `city`, `province`.

```php
$result = persian_kit_validate_national_id('0012345678');

if ($result->isValid()) {
    $province = $result->detail()?->province; // null when the prefix is unlisted
}
```

#### `persian_kit_validate_legal_id(string $id): ValidationResult`

Validates an 11-digit legal entity ID (شناسه ملی), including its checksum.

Detail properties (`LegalIdDetails`): `value`.

#### `persian_kit_validate_phone(string $phone): ValidationResult`

Validates and normalizes an Iranian mobile or landline number.

Accepted forms include `09123456789`, `9123456789`, `+989123456789`, `00989123456789`, `02188887777` and their Persian-digit equivalents.

Detail properties (`PhoneNumberDetails`):

- `type`: `PhoneNumberType::MOBILE` or `PhoneNumberType::LANDLINE`
- `normalizedLocal`, `normalizedE164`
- `operator` (mobile)
- `areaCode`, `city`, `province` (landline; `null` with a warning for an unlisted area code)

```php
$result = persian_kit_validate_phone('+989121234567');

if ($result->isValid()) {
    $local = $result->detail()?->normalizedLocal; // 09121234567
}
```

#### `persian_kit_validate_card_number(string $card): ValidationResult`

Validates a 16-digit bank card number (Luhn checksum). Spaces and dashes are ignored. All-same-digit numbers are rejected.

Detail properties (`CardNumberDetails`): `value`, `bin`, `bank`.

#### `persian_kit_validate_iban(string $iban): ValidationResult`

Validates an Iranian IBAN (شبا) with the mod-97 check. Spaces and dashes are ignored, letters are uppercased, and a bare 24-digit value gets the `IR` prefix. An unlisted bank code is valid with a warning.

Detail properties (`IbanDetails`): `value`, `bankCode`, `bank`.

#### `persian_kit_validate_postal_code(string $code): ValidationResult`

Validates a 10-digit Iranian postal code against the national pattern rules.

Detail properties (`PostalCodeDetails`): `postalCode`, `zoneCode`.

#### `persian_kit_validate_plate_number(string $plate): ValidationResult`

Validates a vehicle licence plate in the `NN[letter]NNN-NN` form, for example `12ب345-67`. Arabic `ي`/`ك` letters are accepted.

Detail properties (`PlateNumberDetails`):

- `twoDigit`, `letter`, `threeDigit`, `cityCode`
- `type`: plate category from the letter (`PlateType`)
- `province`: the province, or several joined with ` - ` for codes issued before a province split
- `provinces`: every province the city code was issued in

#### `persian_kit_validate_bill_id(string $billId, ?string $paymentId = null): ValidationResult`

Validates a utility bill ID (شناسه قبض). Pass `$paymentId` to also validate the payment ID (شناسه پرداخت) and the checksum that ties the two together.

Detail properties (`BillIdDetails`): `billId`, `paymentId` (`null` without a payment ID), `type` (`BillType`: water, electricity, gas, …).

### Number Helpers

#### `persian_kit_number_format(int|float|string $number, string $separator = ','): string`

Adds thousands separators.

- Accepts numeric strings, including Persian digits, `،`/`٬` grouping and the `٫` decimal separator.
- Keeps decimals and the sign.
- Throws `FormatException` for non-numeric strings.

```php
persian_kit_number_format('۱۲۳۴۵۶۷');    // 1,234,567
persian_kit_number_format(1234567, '٬'); // 1٬234٬567
```

#### `persian_kit_number_to_words(int|float $number): string`

Spells a number out in Persian. Supports negatives and decimals (`ممیز`), and returns `صفر` for zero.

Throws `FormatException` for floats beyond `PHP_INT_MAX` or with more precision than a float holds.

```php
persian_kit_number_to_words(123);  // یکصد و بیست و سه
persian_kit_number_to_words(12.5); // دوازده ممیز پنج
```

#### `persian_kit_words_to_number(string $words): int|float|null`

Parses Persian number words back to a number. Returns `null` when the text isn't a number (`دو سه`, `بیست سی`) or the value overflows `PHP_INT_MAX`.

```php
persian_kit_words_to_number('بیست و یک'); // 21
persian_kit_words_to_number('سه صد');     // 300
persian_kit_words_to_number('سلام');      // null
```

#### `persian_kit_ordinal_word(int $n): string`

Returns a Persian ordinal in words. Words ending in `ی` take `ام` with a ZWNJ.

Throws `FormatException` when `$n < 1`.

```php
persian_kit_ordinal_word(3);  // سوم
persian_kit_ordinal_word(30); // سی‌ام
```

#### `persian_kit_ordinal_short(int $n, bool|string $digits = true): string`

Returns a compact ordinal such as `۳ام`.

- `true` (default) uses Persian digits; `false` uses English digits.
- The strings `'persian'` and `'english'` from earlier versions are still accepted.

Throws `FormatException` when `$n < 1`.

```php
persian_kit_ordinal_short(3);        // ۳ام
persian_kit_ordinal_short(3, false); // 3ام
```

#### `persian_kit_time_ago(int|string|DateTimeInterface $timestamp, ?int $now = null, bool $persianDigits = true): string`

Returns relative Persian time text for past and future timestamps.

Throws `FormatException` when a string timestamp can't be parsed.

```php
persian_kit_time_ago(time() - 3600); // ۱ ساعت پیش
```

### Currency Helpers

#### `persian_kit_currency_format(int|float|string $amount, string $unit = 'toman', bool $persianDigits = true, bool $withUnit = true): string`

Formats an amount with `،` thousands separators and the unit name.

```php
persian_kit_currency_format(1500000);                       // ۱،۵۰۰،۰۰۰ تومان
persian_kit_currency_format(1500000, 'rial', false, false); // 1،500،000
```

#### `persian_kit_currency_convert(int|float $amount, string $from, string $to): int|float`

Converts between toman and rial (×10 / ÷10). Returns an `int` when the result is whole.

```php
persian_kit_currency_convert(100, 'toman', 'rial'); // 1000
persian_kit_currency_convert(1235, 'rial', 'toman'); // 123.5
```

Both currency helpers accept `'toman'` or `'rial'` (case-insensitive) and throw `InvalidArgumentException` for any other unit.

### Script Detection

#### `persian_kit_is_persian(string $text, bool $complex = false): bool`

Returns `true` when the text, ignoring whitespace, punctuation and symbols, is entirely Persian script. `$complex = true` also allows Arabic-overlap characters and diacritics.

#### `persian_kit_has_persian(string $text, bool $complex = false): bool`

Returns `true` when any Persian-script character is present.

#### `persian_kit_is_arabic(string $text): bool`

Returns `true` when the text is Arabic script and contains Arabic-only characters. This is intentionally narrower than "contains any Arabic Unicode code point".

#### `persian_kit_has_arabic(string $text): bool`

Returns `true` when Arabic-only characters are present.

## Error Handling Summary

Return a `ValidationResult` for invalid input:

- `persian_kit_validate_national_id`, `persian_kit_validate_legal_id`, `persian_kit_validate_phone`, `persian_kit_validate_card_number`, `persian_kit_validate_iban`, `persian_kit_validate_postal_code`, `persian_kit_validate_plate_number`, `persian_kit_validate_bill_id`

Throw `FormatException` (a `\RuntimeException`) for invalid input:

- `persian_kit_number_format`, `persian_kit_number_to_words`, `persian_kit_time_ago`, `persian_kit_ordinal_word`, `persian_kit_ordinal_short`

Throw `InvalidArgumentException` for an unknown currency unit:

- `persian_kit_currency_format`, `persian_kit_currency_convert`

Return `null` for text that isn't a number:

- `persian_kit_words_to_number`

## Persian Slugs

When the Persian slugs module (`utilities`) and its **Use Persian slugs** option are on (both default), a `sanitize_title` filter runs right after WordPress's own `sanitize_title_with_dashes()`:

- For titles without Persian/Arabic letters it returns WordPress's result unchanged. Latin slugs, WooCommerce attribute taxonomies (`pa_color`) and percent-encoded slugs behave exactly as in core.
- Persian titles are saved with `persian_kit_slug()` rules, so a ZWNJ becomes `-`: `می‌خواهم` is saved as `می-خواهم`.
- When looking a slug up (the `query` context), a ZWNJ is kept, so posts saved by earlier versions with a ZWNJ in their slug still load.
- Posts created before the plugin was activated keep WordPress's percent-encoded slug. When such a URL would 404, the post is found under that slug and served at its own URL.
- A URL that differs from a post's slug only by ZWNJ vs `-` redirects (301) to the post.

Existing slugs are never rewritten. Turn the behavior off with the Persian slugs option, or in code with the `persian_kit_utilities` filter.

The slug keeps its letters in the database and the editor, but links to it are percent-encoded, the form WordPress gives Persian slugs on its own: `get_permalink()` and `get_term_link()` return `/%D8%A8%D8%B1.../` for `/برنامه/`, and browsers show it decoded. A raw UTF-8 link breaks wherever WordPress runs it through `parse_url()`, as `redirect_canonical()` does for `/?p=123`: in some locales (`C.UTF-8` on macOS) PHP takes some bytes of Persian letters for control characters and turns them into `_`. Links of posts, pages, custom post types, attachments and terms are encoded while the Persian slugs module is on, also with its Use Persian slugs option off, since slugs saved while it was on keep their letters. Links that are already encoded and the host name are left alone.

## Jalali Date Archives

While the Jalali dates module (`date_conversion`) is on, date archive URLs whose year is below 1700 are read as Jalali:

- `/1405/` lists the posts of the Jalali year 1405, `/1405/07/` those of Mehr 1405 (23 September to 22 October 2026), and `/1405/07/09/` those of one day. With plain permalinks, `?m=140507` does the same.
- Days that do not exist in the Gregorian calendar, such as 31 Shahrivar (`/1404/06/31/`), load; days that do not exist in the Jalali calendar, such as 31 Mehr, redirect to the month, as an invalid Gregorian day does.
- The page is a normal date archive: `is_month()`, `get_query_var('year')`, `get_query_var('monthnum')` and the `$year` and `$monthnum` globals hold the Jalali parts. The title reads "مهر 1405".
- Gregorian URLs (`/2026/10/`) work as before. Their title names the Jalali months they cover ("مهر – آبان 1405").

Themes that build archive links from the displayed date, such as `get_month_link(get_the_time('Y'), get_the_time('m'))`, get the Jalali archive. `get_year_link()`, `get_month_link()` and `get_day_link()` accept Persian or Arabic digits.

### Archive list

With the module's **Jalali archives and calendar** option on (`jalali_archives`, the default), `wp_get_archives()` lists Jalali periods and links to the Jalali archive pages. This covers the Archives widget and block, in list and dropdown form, and themes that call the function. The `monthly`, `yearly` and `daily` types are Jalali; `weekly`, `postbypost` and `alpha` are unchanged.

- Entries read "مهر 1405" (monthly), "1405" (yearly) or the site's date format (daily), and go through `persian_kit_date_display`, so the Persian digits module's **Jalali dates** option applies.
- `limit`, `order`, `format`, `before`, `after`, `show_post_count` and `post_type` work as in core. The entry for the archive being viewed is selected.
- The list is built from core's own query clauses after `getarchives_where` and `getarchives_join`, so conditions other plugins add there, such as a language plugin's, still apply. Each entry goes through `get_archives_link()` and its filter.

### Calendar

With the same option on, `get_calendar()`, and with it the Calendar widget and block, shows a Jalali month:

- On a Jalali month archive, that month. On a Gregorian month archive, or for a Calendar block set to a Gregorian month, the Jalali month in which that Gregorian month starts. Elsewhere, the current month.
- The table has core's markup (`table#wp-calendar.wp-calendar-table`, `td#today`, `td.pad`, `nav.wp-calendar-nav`), so theme and block styles apply. Weekdays start on the site's **Week Starts On** setting, usually Saturday on Persian sites.
- Days with posts link to the Jalali day archive; the previous and next links go to the nearest Jalali months with posts. Numbers and month names go through `persian_kit_date_display`.

To keep the list and calendar Gregorian in code, use the [`persian_kit_jalali_archives`](#persian_kit_jalali_archives) filter.

With the option off, the list and calendar are WordPress's own. On a Jalali archive page, the calendar then shows the Gregorian month that overlaps the Jalali month most, the one its last day falls in (`/1405/07/` shows October 2026). This needs WordPress 6.8 or later; on earlier versions the calendar reads the Jalali year as Gregorian and shows an empty month.

### Post permalinks

With the module's **Jalali dates in post links** option on (`jalali_permalinks`, off by default) and a permalink structure with the date, such as "Day and name", posts link to their Jalali date: `/1405/07/09/my-post/` instead of `/2026/10/01/my-post/`. "Month and name" gives `/1405/07/my-post/`.

- Only `%year%`, `%monthnum%` and `%day%` change; they are read from the post's date in site time and zero-padded as in core. Structures without them are unchanged. Like core's `pre_post_link`, which this uses, it applies to posts, not pages or custom post types. The editor's permalink preview shows the Jalali date too.
- Addresses built from the permalink follow it: the post's pages (`/2/`), comment pages, feed, embed and attachment pages. `url_to_postid()` resolves Jalali addresses, also in the admin.
- An address in the other calendar redirects (301) to the current one, keeping the rest of the path and the query string. So with the option on, old Gregorian links reach the Jalali address, and after turning it off, Jalali links reach the Gregorian one. Previews are not redirected.
- A post whose slug or date changed is found from its old address under a Jalali date, as WordPress does for Gregorian dates.
- A cut-off address under a Jalali date, such as `/1405/07/09/my-po/`, redirects (301) to the post on that date whose slug starts with it, as WordPress does for Gregorian dates.

The Jalali addresses only work while Persian Kit is active. If it is deactivated, WordPress reads `/1405/07/09/my-post/` as the year 1405 and returns "not found"; the Gregorian addresses work again.

To keep post permalinks Gregorian in code, use the [`persian_kit_jalali_permalinks`](#persian_kit_jalali_permalinks) filter. Redirects between the calendars stay on either way.

## WordPress Hooks

### `persian_kit_loaded`

Fires on `plugins_loaded` (priority 10) once Persian Kit's services are registered. The modules boot later, on `after_setup_theme` (priority 20), so filters added here, or in a theme's `functions.php`, are in place before any module reads them.

```php
add_action('persian_kit_loaded', function () {
    add_filter('persian_kit_digit_conversion', fn (bool $enabled, string $hook) => $hook === 'the_title' ? false : $enabled, 10, 2);
});
```

The `persian_kit_*` helper functions are defined as soon as the plugin file loads, so they do not need this action. To keep a theme working while Persian Kit is inactive, guard each call with `function_exists()` (see [Utilities Guide](UTILITIES.md#guard-calls-when-persian-kit-may-be-inactive)).

### `persian_kit_date_display`

Filters every Jalali date the plugin produces.

```php
add_filter('persian_kit_date_display', function (string $date, string $format, int $timestamp, DateTimeZone $timezone) {
    return $date;
}, 10, 4);
```

### `persian_kit_digit_conversion`

Return `false` to stop digit conversion on one hook. The second argument is the hook name: `the_content`, `the_title`, `get_the_excerpt`, `comment_text`, `widget_text`, `widget_text_content`, `human_time_diff` or `get_the_terms`. With the module's options on, also `persian_kit_date_display` (Jalali dates), `number_format_i18n` (counts), `formatted_woocommerce_price` (WooCommerce prices) and `woocommerce_block_prices` (the script that converts prices drawn by the cart and checkout blocks, loaded on those pages only).

Text inside `<pre>`, `<code>`, `<kbd>` and `<samp>` elements keeps its digits.

```php
add_filter('persian_kit_digit_conversion', function (bool $enabled, string $hook) {
    return $hook === 'the_title' ? false : $enabled;
}, 10, 2);
```

Digit conversion never runs on admin screens, in REST responses or in feeds. The email exclusion is narrow: it skips only text filtered while a `wp_mail` filter is running. Content rendered before `wp_mail()` is called, such as an email template that calls `the_title` or `the_content`, is still converted.

### `persian_kit_char_normalization`

Return `false` to stop character normalization on one integration point:

- `wp_insert_post_data`: posts and menu items on save. Registered when "Fix letters when content is saved" or "Add half-spaces when posts are saved" is on.
- `preprocess_comment`, `pre_term_name`, `pre_term_description`: new comments (content and author name) and terms (name and description, in every taxonomy) on save. Registered when "Fix letters when content is saved" is on.
- `posts_search`: search that matches both spellings.

Search does not rewrite the search terms. For each term it matches the term as typed, its Persian form (ی ک) and its Arabic form (ي ك), each with its digits as typed, in English (1405) and in Persian (۱۴۰۵), in the title, excerpt and content (or the query's `search_columns`). Duplicate forms are dropped, and a term with only one form keeps WordPress's own clause. This applies to the main search query, including WooCommerce's product search on the shop page. Media library searches that also match file names keep WordPress's own query.

### `persian_kit_should_normalize`

Return `false` to skip normalization (and half-spaces) for one post or menu item on save. Public post types and `nav_menu_item` are normalized; auto-drafts, revisions and autosaves never are.

```php
add_filter('persian_kit_should_normalize', function (bool $shouldNormalize, $postContext, array $data, array $postarr) {
    return $shouldNormalize;
}, 10, 4);
```

### `persian_kit_utilities`

Return `false` to turn off a feature of the Persian slugs module (`utilities`). The second argument names the feature: `sanitize_title` (the Persian slug filter; the module's Use Persian slugs option turns it off from the settings page) or `encode_links` (percent-encoding Persian letters in post and term links, see [Persian Slugs](#persian-slugs)).

```php
add_filter('persian_kit_utilities', function (bool $enabled, string $feature) {
    return $feature === 'sanitize_title' ? false : $enabled;
}, 10, 2);
```

### `persian_kit_jalali_archives`

Return `false` to keep the archive list and calendar Gregorian, whatever the option says. It is read once, when the module boots, so add it in a theme's `functions.php` or on `persian_kit_loaded`. The Jalali archive pages still work.

```php
add_filter('persian_kit_jalali_archives', '__return_false');
```

### `persian_kit_jalali_permalinks`

Return `false` to keep post permalinks Gregorian, whatever the option says. It is read once, when the module boots, so add it in a theme's `functions.php` or on `persian_kit_loaded`. Jalali post addresses still open the post, and redirect to the Gregorian ones.

```php
add_filter('persian_kit_jalali_permalinks', '__return_false');
```

### `persian_kit_woocommerce_validate`

Return `false` to skip one checkout check. `$rule` is `phone` or `postcode` (addresses in Iran only, in the classic and block checkout and in My Account > Addresses) or `national_id` (the national ID field, any country). `$group` is `billing` or `shipping`; `national_id` is always `billing`. An empty value is never checked here; WooCommerce's required-field check covers it.

```php
add_filter('persian_kit_woocommerce_validate', function (bool $validate, string $rule, string $group, string $value) {
    return $rule === 'postcode' ? false : $validate;
}, 10, 4);
```

### `persian_kit_woocommerce_cities`

Filters the city suggestions of the [`city_select`](#woocommerce-checkout) option: an array of city names keyed by WooCommerce state code (`THR`, `ESF`, …). Add the villages you deliver to, or drop a province. Keys must be strings and names non-empty strings; anything else is dropped. It runs once per request, when the script is loaded.

```php
add_filter('persian_kit_woocommerce_cities', function (array $cities) {
    $cities['THR'][] = 'امامه';

    return $cities;
});
```

### `persian_kit_acf_jalali_value`

Return `false` to keep an ACF date field's template value Gregorian. `$field` is the ACF field array. See [ACF](#acf).

```php
add_filter('persian_kit_acf_jalali_value', function (bool $jalali, array $field) {
    return $field['name'] === 'event_date' ? false : $jalali;
}, 10, 2);
```

### `persian_kit_conflict_policies`

Filters the built-in compatibility guidance for other Persian plugins.

## WooCommerce Checkout

The WooCommerce module's checkout options (settings page, WooCommerce tab, Checkout and addresses) apply to the classic (shortcode) checkout, the block checkout and My Account > Addresses:

- `checkout_normalize`: Persian and Arabic digits in phone numbers and postcodes become English digits, postcodes lose spaces and dashes, and Arabic ي/ك in names, company, address and city become Persian ی/ک, for every country. The block checkout is fixed in the Store API request (`rest_pre_dispatch`, including batch requests), because WooCommerce's own phone and postcode checks reject Persian digits before any checkout hook runs.
- `checkout_validate`: for addresses in Iran, the phone must pass `persian_kit_validate_phone()` (mobile or landline) and the postcode `persian_kit_validate_postal_code()`. The block checkout reports these errors when the order is placed, as WooCommerce does for its own address checks.
- `national_id` (`off`, `optional`, `required`): a national ID field, checked with `persian_kit_validate_national_id()` and stored in English digits. `required` asks every customer, in any country.
- `city_select`: for Iranian addresses, the city field suggests the province's cities (a native `<datalist>`), in the block and classic checkout, the classic cart's shipping calculator and My Account (the cart block has no address form). The field stays a text field and nothing is checked against the list, so customers can type a village. A user change of province clears a city that is on another province's list only. Browsers without datalist suggestions show a plain text field. The list is the Statistical Centre of Iran's 1403 country-divisions list of cities (`resources/data/ir-cities.json`, copied to `public/data/` by the build), keyed by WooCommerce state code.
- `allowed_states` ("Provinces you deliver to"): a list of WooCommerce's Iran province codes (`THR`, `ABZ`, …); empty, the default, means all provinces. While it has codes, Iranian addresses on the storefront list only those provinces: the block and classic checkout, the classic cart's shipping calculator and My Account > Addresses, for billing and shipping. WooCommerce's own checks refuse any other province, at the classic checkout ("Province is not valid. Please enter one of the following: …") and in the Store API (`invalid_state`); Persian Kit adds the same check to My Account, which WooCommerce leaves out. It is one `woocommerce_states` filter, applied to front-end requests and Store API (`/wc/store/`) requests only, so the shop admin, cron, WP-CLI and other REST routes keep all 31 provinces. Past addresses in a province no longer listed keep its name (`woocommerce_formatted_address_replacements`). With one province, an Iranian address without one (or with a state of another country) reads as that province on the storefront, so both checkouts start with it selected; nothing stored changes until the customer saves. On the settings page, "All provinces" saves an empty list, and "Only these provinces" with nothing ticked does too.

WooCommerce already orders an Iranian address province, city, then address, in both checkouts and in My Account; Persian Kit doesn't change the order.

### National ID

The block checkout field is an additional checkout field with the id `persian-kit/national-id` in the `contact` location. The classic checkout field is `billing_national_id`. Both are saved under one meta key on the order and on the customer:

```
_wc_other/persian-kit/national-id
```

Read it with:

```php
$nationalId = \PersianKit\Modules\WooCommerce\NationalIdField::get($order); // '' when the order has none
```

WooCommerce shows the block field on the order screen, in emails and in My Account for orders placed through the block checkout. Persian Kit shows it on the order screen and in order emails for the other orders, and for every order while the field is off.

## Integrations

An integration is a module that works with another plugin: WooCommerce (`woocommerce`), Contact Form 7 (`cf7`) and ACF (`acf`). Each turns on by itself when its plugin is active, and does nothing while it is not. The plugin is checked when the page loads, by its classes, functions and constants, so a network-activated plugin counts on every site.

- WooCommerce has its own tab on the settings page, shown only while WooCommerce is active, with a card for each section: Checkout and addresses, Prices and currency, Dates. `?tab=woocommerce#checkout` (`#prices`, `#dates`) links to one. While WooCommerce is inactive, `?tab=woocommerce` opens the first tab. Under Dates, `dates_admin` turns the Jalali date pickers on the order, product and coupon screens, and the month filter on the orders list, on or off; dates on orders and in emails follow the Jalali dates module.
- The Integrations tab has a card for each other integration, grouped as Forms, Store and Compatibility. A plugin that is active but too old, or that needs an add-on (such as a Pro version), has a card that says why, with its switch disabled.
- Plugins that are not active are listed under "Also works with", each with a link to its WordPress.org page. One that was set up on this site before says its settings are kept: stored settings stay until the plugin is active again.
- A card is marked New until the Integrations or WooCommerce tab is opened once after its plugin was activated. The keys of the integrations that have been seen are stored per site in the `persian_kit_seen_integrations` option.
- Integrations add no admin notices. Advice about another Persian plugin that does the same work is shown at the top of the settings page, as before, and on the card or tab of the integration it concerns.

Values are stored in their standard form, with English digits and Gregorian dates, and shown as Persian digits and Jalali dates where people read them. Exports and other plugins keep working with the stored values.

### Turning an integration off

A field type Persian Kit adds to a form plugin keeps rendering while the integration is off, as a plain text input that accepts any text and is not checked. Forms built with it never show the raw form tag. The integration's card names the forms that use these fields when it is switched off, before it is saved.

If Persian Kit itself is deactivated, its field types are gone: Contact Form 7 then prints a tag such as `[national_id your-id]` as text. Replace these tags before deactivating Persian Kit.

### Writing an integration

A module becomes an integration by returning a category and the plugins it needs (`src/Contracts/ModuleInterface.php`):

- `category()`: `forms`, `commerce` or `compat`; `null` for modules that need no other plugin.
- `requiredPlugins()`: the plugin it integrates with, then any add-on it also needs. Each has a `name`, a `check` callable, and optionally a `version` callable, a `minVersion` and its WordPress.org `slug` (for the link on its card).
- `isAvailable()` and `unavailableReason()` (built on `requiredPlugins()` in `AbstractModule`): the reason's code is `AbstractModule::REASON_INACTIVE`, `REASON_OUTDATED` or `REASON_MISSING`.
- `boot()` runs while the module is on and its plugins are available. `bootDisabled()` runs while it is off and they are available: register fallbacks there, such as plain inputs for its field types.
- `formsUsingFields()`: the forms that use its field types, for the warning shown when it is switched off.

`PersianKit\Modules\Forms\IranianFieldTypes` defines the Iranian field types once (label, input attributes, error message, validation and the stored form) for every form plugin; their group is called "Iranian fields" in the form editor.

## Forms

Contact Form 7 (`cf7`) and ACF (`acf`) are separate integrations, each with its own switch.

### Contact Form 7

- `[date]` fields get the Jalali date picker and still submit `Y-m-d`, so CF7's own `min:`/`max:` checks, mail tags and storage work as before. Without JavaScript they are text fields, and a typed Jalali date (`۱۴۰۵/۷/۱۰`) is converted before CF7 checks it. Write `[date name gregorian]` to keep CF7's own date input.
- While the Jalali dates module is on, `[name]` in an email shows the Jalali date in the site's date format. `[_raw_name]` and `[_format_name "Y-m-d"]` keep the Gregorian date.
- Persian and Arabic digits in `[tel]`, `[number]`, `[range]` and `[date]` fields become English digits before CF7 checks them.
- Iranian fields, each with a `*` variant for a required field and CF7's usual text field options (`id:`, `class:`, `placeholder`, `size:`, `maxlength:`, `autocomplete:`, `readonly`, a default value):

| Form tag | Checked with | Sent as |
| --- | --- | --- |
| `[mobile_ir name]` | `persian_kit_validate_phone()`, mobile numbers only | `09121234567` |
| `[national_id name]` | `persian_kit_validate_national_id()` | 10 digits |
| `[postcode_ir name]` | `persian_kit_validate_postal_code()` | 10 digits |
| `[card_ir name]` | `persian_kit_validate_card_number()` | 16 digits |
| `[iban_ir name]` | `persian_kit_validate_iban()` | `IR` and 24 digits |

Their error messages are on each form's Messages tab. In the form editor (Contact Form 7 6.0 or newer), a button for each one opens CF7's tag generator.

While the integration is off, the Iranian fields are plain text inputs: nothing is checked or changed, and their buttons are gone from the form editor. The settings page lists the forms that use them, from a scan of the forms' templates that is cached and cleared when a form is saved, trashed or deleted.

### ACF

- Date Picker and Date Time Picker fields get the Jalali date picker on edit screens, in ACF blocks and in `acf_form()`. Values are stored as ACF stores them (`Ymd`, `Y-m-d H:i:s`). Fields from ACF 4 with a `save_format` keep ACF's own picker.
- While the Jalali dates module is on, the formatted value (`get_field()`, `the_field()`) is a Jalali date in the field's return format: `Y/m/d` returns `1405/07/10`. Return formats that code parses (`Ymd`, `Y-m-d`, `Y-m-d H:i:s`, `U`, `c` and the like) stay Gregorian, as do REST API responses and the unformatted value (`get_field('name', $post_id, false)`). If your theme parses a formatted value such as `d/m/Y`, read the unformatted value or use the `persian_kit_acf_jalali_value` filter.

## WP-CLI

Character normalization has a CLI command. It is available even when the Persian ی and ک module (`char_normalization`) is off, and uses that module's saved settings:

```bash
wp persian-kit normalize [--dry-run] [--post-type=post,page] [--batch-size=100] [--restart]
```

- `--dry-run` counts the posts the current settings would change, by post type, without saving anything.
- `--batch-size` is clamped to 1–500.
- Progress is stored in the same job as the batch tool on the settings page's Tools tab, so either one can resume a run the other left unfinished.

## Module Keys

Settings are stored in the `persian_kit_settings` option, per module under these keys. Stored values are read on top of each module's defaults, so a key added in an update takes its default until the settings are saved.

The settings page (the Persian Kit menu) has five tabs: Display and Writing hold the core modules, WooCommerce (only while WooCommerce is active) and Integrations hold the [integrations](#integrations), and Tools holds the batch tool that fixes letters in existing posts. One form spans the module tabs, so Save sends every module's settings; `AdminPage::GROUPS` maps each core module key to its tab, and `?tab=` opens one. A tab that is not shown, or a module whose plugin is not active, sends nothing, so its stored settings are kept.

| Module key | On the settings page | Settings (new-install default) |
| --- | --- | --- |
| `digit_conversion` | Display > Persian digits | `enabled` (off), `dates`, `numbers`, `prices` (on) |
| `date_conversion` | Display > Jalali dates | `enabled` (on), `global_conversion` (off), `jalali_archives` (on), `jalali_permalinks` (off) |
| `admin_font` | Display > Admin font | `enabled` (on), `font` (`vazirmatn`, `noto-sans-arabic`, `ibm-plex-sans-arabic`; default `vazirmatn`) |
| `char_normalization` | Writing > Persian ی and ک | `enabled` (on), `normalize_on_save` (off), `teh_marbuta` (off), `half_space_fix` (off) |
| `zwnj_editor` | Writing > Half-space key | `enabled` (on) |
| `utilities` | Writing > Persian slugs | `enabled` (on), `persian_slugs` (on) |
| `woocommerce` | WooCommerce | `enabled` (on), `checkout_normalize` (on), `checkout_validate` (on), `national_id` (`off`), `city_select` (off), `allowed_states` (`[]`, all provinces), `dates_admin` (on) |
| `cf7` | Integrations > Forms > Contact Form 7 | `enabled` (on) |
| `acf` | Integrations > Forms > ACF | `enabled` (on) |

The option is registered with the Settings API (group `persian_kit`), so every write is sanitized, whether it comes from the settings page or from `update_option()`. Each module's values are merged over what is stored and sanitized by the module; a module left out keeps its stored values, and keys that are not module keys are dropped. Booleans are stored as `true`/`false`.

`persian_kit_db_version` records the settings schema version. Sites upgraded from a version before 2 keep their earlier behaviour: digit conversion stays as it was (with the new `dates`, `numbers` and `prices` options off) and `normalize_on_save` is on. Version 3 replaced the `forms` key with `cf7` and `acf`: each is on when `forms` and its option (`forms.cf7`, `forms.acf`) were both on.
