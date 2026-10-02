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

See [Persian slugs](#persian-slugs) for how the Utilities module applies this to post slugs.

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

When the Utilities module and its **Persian slugs** option are on (both default), a `sanitize_title` filter runs right after WordPress's own `sanitize_title_with_dashes()`:

- For titles without Persian/Arabic letters it returns WordPress's result unchanged. Latin slugs, WooCommerce attribute taxonomies (`pa_color`) and percent-encoded slugs behave exactly as in core.
- Persian titles are saved with `persian_kit_slug()` rules, so a ZWNJ becomes `-`: `می‌خواهم` is saved as `می-خواهم`.
- When looking a slug up (the `query` context), a ZWNJ is kept, so posts saved by earlier versions with a ZWNJ in their slug still load.
- Posts created before the plugin was activated keep WordPress's percent-encoded slug. When such a URL would 404, the post is found under that slug and served at its own URL.
- A URL that differs from a post's slug only by ZWNJ vs `-` redirects (301) to the post.

Existing slugs are never rewritten. Turn the behavior off with the Persian slugs option, or in code with the `persian_kit_utilities` filter.

The slug keeps its letters in the database and the editor, but links to it are percent-encoded, the form WordPress gives Persian slugs on its own: `get_permalink()` and `get_term_link()` return `/%D8%A8%D8%B1.../` for `/برنامه/`, and browsers show it decoded. A raw UTF-8 link breaks wherever WordPress runs it through `parse_url()`, as `redirect_canonical()` does for `/?p=123`: in some locales (`C.UTF-8` on macOS) PHP takes some bytes of Persian letters for control characters and turns them into `_`. Links of posts, pages, custom post types, attachments and terms are encoded while the Utilities module is on, also with the Persian slugs option off, since slugs saved while it was on keep their letters. Links that are already encoded and the host name are left alone.

## Jalali Date Archives

While the Date Conversion module is on, date archive URLs whose year is below 1700 are read as Jalali:

- `/1405/` lists the posts of the Jalali year 1405, `/1405/07/` those of Mehr 1405 (23 September to 22 October 2026), and `/1405/07/09/` those of one day. With plain permalinks, `?m=140507` does the same.
- Days that do not exist in the Gregorian calendar, such as 31 Shahrivar (`/1404/06/31/`), load; days that do not exist in the Jalali calendar, such as 31 Mehr, redirect to the month, as an invalid Gregorian day does.
- The page is a normal date archive: `is_month()`, `get_query_var('year')`, `get_query_var('monthnum')` and the `$year` and `$monthnum` globals hold the Jalali parts. The title reads "مهر 1405".
- Gregorian URLs (`/2026/10/`) work as before. Their title names the Jalali months they cover ("مهر – آبان 1405").

Themes that build archive links from the displayed date, such as `get_month_link(get_the_time('Y'), get_the_time('m'))`, get the Jalali archive. `get_year_link()`, `get_month_link()` and `get_day_link()` accept Persian or Arabic digits.

### Archive list

With the module's **Show the archive list and calendar in Jalali** option on (`jalali_archives`, the default), `wp_get_archives()` lists Jalali periods and links to the Jalali archive pages. This covers the Archives widget and block, in list and dropdown form, and themes that call the function. The `monthly`, `yearly` and `daily` types are Jalali; `weekly`, `postbypost` and `alpha` are unchanged.

- Entries read "مهر 1405" (monthly), "1405" (yearly) or the site's date format (daily), and go through `persian_kit_date_display`, so the Digit Conversion **Jalali dates** option applies.
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

With the module's **Use Jalali dates in post permalinks** option on (`jalali_permalinks`, off by default) and a permalink structure with the date, such as "Day and name", posts link to their Jalali date: `/1405/07/09/my-post/` instead of `/2026/10/01/my-post/`. "Month and name" gives `/1405/07/my-post/`.

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

Return `false` to stop digit conversion on one hook. The second argument is the hook name: `the_content`, `the_title`, `get_the_excerpt`, `comment_text`, `widget_text`, `widget_text_content`, `human_time_diff` or `get_the_terms`. With the module's options on, also `persian_kit_date_display` (Jalali dates), `number_format_i18n` (counts) and `formatted_woocommerce_price` (WooCommerce prices).

Text inside `<pre>`, `<code>`, `<kbd>` and `<samp>` elements keeps its digits.

```php
add_filter('persian_kit_digit_conversion', function (bool $enabled, string $hook) {
    return $hook === 'the_title' ? false : $enabled;
}, 10, 2);
```

Digit conversion never runs on admin screens, in REST responses or in feeds. The email exclusion is narrow: it skips only text filtered while a `wp_mail` filter is running. Content rendered before `wp_mail()` is called, such as an email template that calls `the_title` or `the_content`, is still converted.

### `persian_kit_char_normalization`

Return `false` to stop character normalization on one integration point: `wp_insert_post_data` (on save, only registered when "Fix letters when posts are saved" is on) or `posts_search` (search that matches both Arabic and Persian Yeh and Kaf).

Search does not rewrite the search terms. For each term it matches the term as typed, its Persian form (ی ک) and its Arabic form (ي ك), in the title, excerpt and content (or the query's `search_columns`). Digits are matched as typed; whether Persian and Latin digits match depends on the database collation. Media library searches that also match file names keep WordPress's own query.

### `persian_kit_should_normalize`

Return `false` to skip normalization for one post on save.

```php
add_filter('persian_kit_should_normalize', function (bool $shouldNormalize, $postContext, array $data, array $postarr) {
    return $shouldNormalize;
}, 10, 4);
```

### `persian_kit_utilities`

Return `false` to turn off a Utilities module feature. The second argument names the feature: `sanitize_title` (the Persian slug filter; the module's Persian slugs option turns it off from the settings screen) or `encode_links` (percent-encoding Persian letters in post and term links, see [Persian Slugs](#persian-slugs)).

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

### `persian_kit_conflict_policies`

Filters the built-in compatibility guidance for other Persian plugins.

## WP-CLI

Character normalization has a CLI command. It is available even when the Character Normalization module is off, and uses that module's saved settings:

```bash
wp persian-kit normalize [--dry-run] [--post-type=post,page] [--batch-size=100] [--restart]
```

- `--dry-run` counts the posts the current settings would change, by post type, without saving anything.
- `--batch-size` is clamped to 1–500.
- Progress is stored in the same job as the settings screen's batch tool, so either one can resume a run the other left unfinished.

## Module Keys

Settings are stored in the `persian_kit_settings` option, per module under these keys. Stored values are read on top of each module's defaults, so a key added in an update takes its default until the settings are saved.

| Module key | Settings (new-install default) |
| --- | --- |
| `date_conversion` | `enabled` (on), `global_conversion` (off), `jalali_archives` (on), `jalali_permalinks` (off) |
| `digit_conversion` | `enabled` (off), `dates`, `numbers`, `prices` (on) |
| `char_normalization` | `enabled` (on), `normalize_on_save` (off), `teh_marbuta` (off) |
| `admin_font` | `enabled` (on), `font` (`vazirmatn`) |
| `zwnj_editor` | `enabled` (on) |
| `woocommerce` | `enabled` (on) |
| `utilities` | `enabled` (on), `persian_slugs` (on) |

The option is registered with the Settings API (group `persian_kit`), so every write is sanitized, whether it comes from the settings screen or from `update_option()`. Each module's values are merged over what is stored and sanitized by the module; a module left out keeps its stored values, and keys that are not module keys are dropped. Booleans are stored as `true`/`false`.

`persian_kit_db_version` records the settings schema version. Sites upgraded from a version before 2 keep their earlier behaviour: digit conversion stays as it was (with the new `dates`, `numbers` and `prices` options off) and `normalize_on_save` is on.
