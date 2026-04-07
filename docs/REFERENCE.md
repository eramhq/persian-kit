# Reference

This document is the developer-facing API reference for Persian Kit. It focuses on callable PHP functions, return contracts, exception behavior, and integration points.

## Runtime Model

- Public helpers are defined in [src/functions.php](/Users/navidkashani/Local%20Sites/persian-kit/app/public/wp-content/plugins/persian-kit/src/functions.php).
- Most helpers are pure static wrappers around utility classes.
- Validation helpers do not throw for invalid user input. They return `ValidationResult`.
- Some formatter helpers do throw `InvalidArgumentException` when the input cannot be interpreted safely.

## ValidationResult Contract

Validation helpers return `PersianKit\Modules\Utilities\ValidationResult`.

Methods:

- `isValid(): bool`
- `errors(): array`
- `details(): array`

Typical usage:

```php
$result = pk_validate_phone('09123456789');

if (!$result->isValid()) {
    wp_send_json_error([
        'errors' => $result->errors(),
    ], 422);
}

$details = $result->details();
```

Behavior notes:

- `errors()` is always an array of Persian error strings.
- `details()` is always an array.
- Success returns `[]` for `errors()`.
- Failure may still include `details()` when useful, although current validators mostly return detail payloads only on success.

## Public PHP API

### Date Helpers

#### `pk_date(string $format, int|string $timestamp = '', ?DateTimeZone $timezone = null): string`

Formats a Gregorian timestamp as Jalali using PHP-style date format tokens.

Notes:

- Intended for explicit developer use.
- Returns English digits. Digit conversion is a separate concern.
- Accepts Unix timestamps and strtotime-compatible strings.

Example:

```php
echo pk_date('Y/m/d', time());
```

#### `pk_gregorian_date(string $format, int|string $timestamp = '', ?DateTimeZone $timezone = null): string`

Formats a timestamp as Gregorian explicitly, bypassing Jalali conversion.

Use this when you need machine-oriented or interoperable output.

### Digit Conversion

#### `pk_to_persian_digits(string $text): string`

Converts ASCII and Arabic-Indic digits to Persian digits.

```php
pk_to_persian_digits('Order 123'); // Order ۱۲۳
```

#### `pk_to_english_digits(string $text): string`

Converts Persian and Arabic-Indic digits to ASCII digits.

Use this before numeric validation or database normalization.

#### `pk_to_arabic_digits(string $text): string`

Converts ASCII and Persian digits to Arabic-Indic digits.

### Text Normalization

#### `pk_normalize_persian(string $text): string`

Normalizes Persian-adjacent Arabic characters for Persian usage.

Current normalization includes:

- Arabic Yeh -> Persian Yeh
- Arabic Kaf -> Persian Kaf
- Arabic digits -> Persian digits

This helper uses the default normalizer behavior. It does not expose module settings like `teh_marbuta`.

#### `pk_slug(string $text): string`

Generates a URL-safe slug while preserving Persian letters.

Behavior:

- Normalizes Arabic Yeh/Kaf first
- Converts digits to English
- Lowercases Latin characters
- Replaces whitespace and underscores with `-`
- Preserves Persian Unicode letters and ZWNJ
- Removes unsupported characters
- Collapses repeated hyphens

```php
pk_slug('نمونه نوشته ۱۴۰۵'); // نمونه-نوشته-1405
```

### Validation Helpers

#### `pk_validate_national_id(string $id): ValidationResult`

Validates an Iranian national ID.

Input handling:

- Trims whitespace
- Accepts Persian and Arabic digits
- Removes spaces and hyphens
- Left-pads 8-9 digit inputs to 10 digits

Validation rules:

- Must resolve to 10 digits
- Rejects repeated-digit patterns
- Rejects sequential invalid pattern `0123456789`
- Rejects invalid city code prefixes
- Applies checksum validation

Success details:

- `city_code: string`
- `city: ?string`
- `province: ?string`

```php
$result = pk_validate_national_id('0012345678');

if ($result->isValid()) {
    $details = $result->details();
    $province = $details['province'];
}
```

#### `pk_validate_legal_id(string $id): ValidationResult`

Validates an Iranian legal entity identifier.

Input handling:

- Trims whitespace
- Accepts Persian and Arabic digits

Validation rules:

- Must be 11 digits
- Middle six digits cannot all be zero
- Applies checksum validation

Success details:

- No structured detail payload currently returned

#### `pk_validate_phone(string $phone): ValidationResult`

Validates and normalizes an Iranian mobile number.

Accepted forms include:

- `09123456789`
- `9123456789`
- `+989123456789`
- `00989123456789`
- Persian-digit equivalents

Validation rules:

- Mobile only
- Must normalize to `09xxxxxxxxx`

Success details:

- `normalized_local: string`
- `normalized_e164: string`
- `operator: ?string`
- `type: 'mobile'`

```php
$result = pk_validate_phone('+989121234567');

if ($result->isValid()) {
    $normalizedLocal = $result->details()['normalized_local']; // 09121234567
    $normalizedE164 = $result->details()['normalized_e164'];   // +989121234567
}
```

#### `pk_validate_card_number(string $card): ValidationResult`

Validates a 16-digit Iranian bank card number.

Input handling:

- Trims whitespace
- Accepts Persian and Arabic digits
- Removes spaces and hyphens

Validation rules:

- Must be 16 digits
- Must pass Luhn checksum

Success details:

- `bank: ?string`
- `bin: string`

#### `pk_validate_iban(string $iban): ValidationResult`

Validates an Iranian Sheba/IBAN.

Input handling:

- Trims whitespace
- Accepts Persian and Arabic digits
- Removes spaces
- Uppercases alpha characters
- Auto-prefixes `IR` when a bare 24-digit value is passed

Validation rules:

- Must be an Iranian IBAN
- Must pass mod-97 validation

Success details:

- `bank_code: string`
- `bank: ?string`

```php
$result = pk_validate_iban('IR820540102680020817909002');
```

### Formatting Helpers

#### `pk_number_to_words(int|float $number): string`

Converts a number to Persian words.

Behavior:

- Supports negative numbers
- Supports decimal values using `ممیز`
- Returns `صفر` for zero

```php
pk_number_to_words(123);      // یکصد و بیست و سه
pk_number_to_words(12.5);     // دوازده ممیز پنج
```

#### `pk_number_format(int|float|string $number, string $separator = ','): string`

Adds thousands separators to a numeric value.

Behavior:

- Accepts `int`, `float`, or numeric string
- Accepts Persian-digit input strings
- Preserves decimal portions
- Preserves leading minus sign

Throws:

- `InvalidArgumentException` for non-numeric strings

```php
pk_number_format('۱۲۳۴۵۶۷');   // 1,234,567
pk_number_format(1234567, '٬'); // 1٬234٬567
```

#### `pk_time_ago(int|string|DateTimeInterface $timestamp, ?int $now = null, bool $persianDigits = true): string`

Returns relative Persian time text.

Behavior:

- Supports past and future timestamps
- Accepts Unix timestamps, strtotime-compatible strings, and `DateTimeInterface`
- Uses Persian digits by default

Throws:

- `InvalidArgumentException` when a string timestamp cannot be parsed

```php
pk_time_ago(time() - 3600); // ۱ ساعت پیش
```

#### `pk_ordinal_word(int $n): string`

Returns a Persian ordinal in words.

Throws:

- `InvalidArgumentException` when `n < 1`

Example:

```php
pk_ordinal_word(3); // سوم
```

#### `pk_ordinal_short(int $n, string $digits = 'persian'): string`

Returns a compact ordinal such as `۳ام`.

Arguments:

- `digits = 'persian'` converts the numeric part to Persian digits
- Any other value leaves digits as ASCII

Throws:

- `InvalidArgumentException` when `n < 1`

### Script Detection

#### `pk_is_persian(string $text, bool $complex = false): bool`

Returns `true` only when the stripped text is entirely Persian-script according to the plugin rules.

Notes:

- Ignores whitespace, punctuation, and symbols before checking
- `complex = true` allows more Arabic-overlap characters and diacritics

#### `pk_has_persian(string $text, bool $complex = false): bool`

Returns `true` when any Persian-script character is present.

#### `pk_is_arabic(string $text): bool`

Returns `true` when the stripped text is Arabic-script and contains Arabic-exclusive characters recognized by the plugin.

This is intentionally narrower than “contains any Arabic Unicode codepoint”.

#### `pk_has_arabic(string $text): bool`

Returns `true` when Arabic-exclusive characters are present.

## Error Handling Summary

Returns `ValidationResult` on invalid input:

- `pk_validate_national_id`
- `pk_validate_legal_id`
- `pk_validate_phone`
- `pk_validate_card_number`
- `pk_validate_iban`

Throws `InvalidArgumentException` on invalid input:

- `pk_number_format`
- `pk_time_ago`
- `pk_ordinal_word`
- `pk_ordinal_short`

## WordPress Hooks

### `persian_kit_date_display`

Filter for Jalali date output formatting.

### `persian_kit_digit_conversion`

Filter to control digit conversion by context.

### `persian_kit_char_normalization`

Filter to enable or disable normalization hooks for specific integration points.

### `persian_kit_should_normalize`

Filter to skip normalization for specific posts during save.

Expected callback shape:

```php
add_filter('persian_kit_should_normalize', function (bool $shouldNormalize, $postContext, array $data, array $postarr) {
    return $shouldNormalize;
}, 10, 4);
```

### `persian_kit_conflict_policies`

Filter to alter built-in compatibility/conflict policy definitions.

### `persian_kit_known_conflicts`

Alias-style extension point for adding or altering known conflict policies.

## WP-CLI

Character normalization exposes a CLI command:

```bash
wp persian-kit normalize [--dry-run] [--post-type=post,page] [--batch-size=100] [--restart]
```

## Module Keys

Settings are stored per module under these keys:

- `date_conversion`
- `digit_conversion`
- `char_normalization`
- `admin_font`
- `zwnj_editor`
- `woocommerce`
- `utilities`
