---
title: "PHP API"
description: "Helper signatures, validation results, formatting and date-field APIs."
---
# PHP API

## Runtime Model

- Public helpers are defined in [src/functions.php](../../../src/functions.php).
- Most helpers are thin wrappers around the bundled [eram/abzar](https://github.com/eramhq/abzar-php) (text, numbers, validation) and [eram/daynum](https://github.com/eramhq/daynum) (Jalali calendar) libraries.
- Both libraries are bundled under the `PersianKit\Dependencies\` namespace, to isolate their class names from another plugin's unscoped copy. Their classes are named below with that prefix.
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

- Produces English digits before `persian_kit_date_display`; enabled digit conversion or another filter may change the result.
- Accepts Unix timestamps and `strtotime()`-compatible strings. Strings without a timezone follow PHP's default timezone (normally UTC in WordPress). `0` and `''` mean now; unparseable strings are cast from `strtotime()` failure to timestamp zero. Prefer an explicit valid Unix timestamp.
- Uses the site timezone unless `$timezone` is given.
- The output passes through the `persian_kit_date_display` filter.
- It never adds the Gregorian date, even with **Show the Gregorian date too** on. That option applies to post and comment dates only.

```php
echo persian_kit_date('Y/m/d', time());
```

#### `persian_kit_gregorian_date(string $format, int|string $timestamp = '', ?DateTimeZone $timezone = null): string`

Formats a timestamp as a Gregorian date, bypassing Jalali conversion even when global conversion of `wp_date()` is on.

Use it for machine-oriented or interoperable output.

#### `persian_kit_jalali_to_gregorian(string $date, string $format = 'Y-m-d'): ?string`

Reads a date typed in Jalali, or Gregorian, and returns it as a Gregorian date in `$format`. Persian, Arabic or English digits, `-`, `/` or `.` between the parts, and a time after the date (site time) are read. Years from 1200 to 1600 are Jalali and from 1700 on Gregorian. Returns `null` when the value is not a valid date.

```php
persian_kit_jalali_to_gregorian('1403/05/12');                    // '2024-08-02'
persian_kit_jalali_to_gregorian('۱۴۰۳-۰۵-۱۲ ۱۸:۳۰', 'Y-m-d H:i');  // '2024-08-02 18:30'
persian_kit_jalali_to_gregorian('1403/12/31');                    // null: 1403 has 30 days in Esfand
```

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

See [Persian slugs](../links.md) for how the Persian slugs module applies this to post slugs.

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


## Deterministic example

The documentation runner loads bundled helpers with UTC and a pass-through date filter; no WordPress database is loaded. A live site can filter date output.

```php
echo persian_kit_to_persian_digits('Order 123'), "\n";
echo persian_kit_normalize_persian('كتاب يكي ١٢'), "\n";
echo persian_kit_slug('نمونه نوشته ۱۴۰۵'), "\n";
echo persian_kit_half_space_fix('می خواهم کتاب ها را'), "\n";
echo persian_kit_date('Y/m/d', 1790899200, new DateTimeZone('UTC')), "\n";
echo persian_kit_jalali_to_gregorian('1405/07/10'), "\n";
echo persian_kit_validate_phone('+989121234567')->detail()->normalizedLocal, "\n";
```

```text
Order ۱۲۳
کتاب یکی ۱۲
نمونه-نوشته-1405
می‌خواهم کتاب‌ها را
1405/07/10
2026-10-02
09121234567
```

Related: [hooks](hooks.md), [recipes](recipes.md), [date behavior](../dates.md).

## Formatting examples

These use the same isolated bootstrap and a fixed clock.

```php
echo persian_kit_to_english_digits('۱۲۳'), "\n";
echo persian_kit_to_arabic_digits('123'), "\n";
echo persian_kit_keyboard_fix('sghl'), "\n";
echo persian_kit_keyboard_fix('سلام'), "\n";
echo persian_kit_number_format('۱۲۳۴۵۶۷'), "\n";
echo persian_kit_number_format(1234567, '٬'), "\n";
echo persian_kit_number_to_words(123), "\n";
echo persian_kit_number_to_words(12.5), "\n";
echo persian_kit_words_to_number('بیست و یک'), "\n";
echo persian_kit_words_to_number('سه صد'), "\n";
echo json_encode(persian_kit_words_to_number('سلام')), "\n";
echo persian_kit_ordinal_word(3), "\n";
echo persian_kit_ordinal_word(30), "\n";
echo persian_kit_ordinal_short(3), "\n";
echo persian_kit_ordinal_short(3, false), "\n";
echo persian_kit_currency_format(1500000), "\n";
echo persian_kit_currency_format(1500000, 'rial', false, false), "\n";
echo persian_kit_currency_convert(100, 'toman', 'rial'), "\n";
echo persian_kit_currency_convert(1235, 'rial', 'toman'), "\n";
echo persian_kit_time_ago(1700000000, 1700003600), "\n";
echo persian_kit_jalali_to_gregorian('۱۴۰۳-۰۵-۱۲ ۱۸:۳۰', 'Y-m-d H:i'), "\n";
echo json_encode(persian_kit_jalali_to_gregorian('1403/12/31')), "\n";
echo persian_kit_gregorian_date('Y-m-d', 1790899200, new DateTimeZone('UTC')), "\n";
```

```text
123
١٢٣
سلام
sghl
1,234,567
1٬234٬567
یکصد و بیست و سه
دوازده ممیز پنج
21
300
null
سوم
سی‌ام
۳ام
3ام
۱،۵۰۰،۰۰۰ تومان
1،500،000
1000
123.5
۱ ساعت پیش
2024-08-02 18:30
null
2026-10-02
```
