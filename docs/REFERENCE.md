# Reference

Compatibility index: the full reference now lives in [English](en/overview.md) and [Persian](fa/overview.md). Historical heading links below are retained; this index is not public website navigation.

## Runtime Model

See [the maintained guide](en/developers/api.md).

## ValidationResult Contract

See [the maintained guide](en/developers/api.md).

## Public PHP API

See [the maintained guide](en/developers/api.md).

### Date Helpers

See [the maintained guide](en/developers/api.md).

#### `persian_kit_date(string $format, int|string $timestamp = '', ?DateTimeZone $timezone = null): string`

See [the maintained guide](en/developers/api.md).

#### `persian_kit_gregorian_date(string $format, int|string $timestamp = '', ?DateTimeZone $timezone = null): string`

See [the maintained guide](en/developers/api.md).

#### `persian_kit_jalali_to_gregorian(string $date, string $format = 'Y-m-d'): ?string`

See [the maintained guide](en/developers/api.md).

#### `persian_kit_date_field_attributes(array $options = []): string`

See [the maintained guide](en/developers/api.md).

### Digit Conversion

See [the maintained guide](en/developers/api.md).

#### `persian_kit_to_persian_digits(string $text): string`

See [the maintained guide](en/developers/api.md).

#### `persian_kit_to_english_digits(string $text): string`

See [the maintained guide](en/developers/api.md).

#### `persian_kit_to_arabic_digits(string $text): string`

See [the maintained guide](en/developers/api.md).

### Text Helpers

See [the maintained guide](en/developers/api.md).

#### `persian_kit_normalize_persian(string $text): string`

See [the maintained guide](en/developers/api.md).

#### `persian_kit_slug(string $text): string`

See [the maintained guide](en/developers/api.md).

#### `persian_kit_half_space_fix(string $text): string`

See [the maintained guide](en/developers/api.md).

#### `persian_kit_keyboard_fix(string $text): string`

See [the maintained guide](en/developers/api.md).

#### `persian_kit_persian_sort(array $items, ?callable $key = null): array`

See [the maintained guide](en/developers/api.md).

### Validation Helpers

See [the maintained guide](en/developers/api.md).

#### `persian_kit_validate_national_id(string $id): ValidationResult`

See [the maintained guide](en/developers/api.md).

#### `persian_kit_validate_legal_id(string $id): ValidationResult`

See [the maintained guide](en/developers/api.md).

#### `persian_kit_validate_phone(string $phone): ValidationResult`

See [the maintained guide](en/developers/api.md).

#### `persian_kit_validate_card_number(string $card): ValidationResult`

See [the maintained guide](en/developers/api.md).

#### `persian_kit_validate_iban(string $iban): ValidationResult`

See [the maintained guide](en/developers/api.md).

#### `persian_kit_validate_postal_code(string $code): ValidationResult`

See [the maintained guide](en/developers/api.md).

#### `persian_kit_validate_plate_number(string $plate): ValidationResult`

See [the maintained guide](en/developers/api.md).

#### `persian_kit_validate_bill_id(string $billId, ?string $paymentId = null): ValidationResult`

See [the maintained guide](en/developers/api.md).

### Number Helpers

See [the maintained guide](en/developers/api.md).

#### `persian_kit_number_format(int|float|string $number, string $separator = ','): string`

See [the maintained guide](en/developers/api.md).

#### `persian_kit_number_to_words(int|float $number): string`

See [the maintained guide](en/developers/api.md).

#### `persian_kit_words_to_number(string $words): int|float|null`

See [the maintained guide](en/developers/api.md).

#### `persian_kit_ordinal_word(int $n): string`

See [the maintained guide](en/developers/api.md).

#### `persian_kit_ordinal_short(int $n, bool|string $digits = true): string`

See [the maintained guide](en/developers/api.md).

#### `persian_kit_time_ago(int|string|DateTimeInterface $timestamp, ?int $now = null, bool $persianDigits = true): string`

See [the maintained guide](en/developers/api.md).

### Currency Helpers

See [the maintained guide](en/developers/api.md).

#### `persian_kit_currency_format(int|float|string $amount, string $unit = 'toman', bool $persianDigits = true, bool $withUnit = true): string`

See [the maintained guide](en/developers/api.md).

#### `persian_kit_currency_convert(int|float $amount, string $from, string $to): int|float`

See [the maintained guide](en/developers/api.md).

### Script Detection

See [the maintained guide](en/developers/api.md).

#### `persian_kit_is_persian(string $text, bool $complex = false): bool`

See [the maintained guide](en/developers/api.md).

#### `persian_kit_has_persian(string $text, bool $complex = false): bool`

See [the maintained guide](en/developers/api.md).

#### `persian_kit_is_arabic(string $text): bool`

See [the maintained guide](en/developers/api.md).

#### `persian_kit_has_arabic(string $text): bool`

See [the maintained guide](en/developers/api.md).

## Error Handling Summary

See [the maintained guide](en/developers/api.md).

## Persian Slugs

See [the maintained guide](en/links.md).

## Jalali Date Archives

See [the maintained guide](en/links.md).

### Archive list

See [the maintained guide](en/links.md).

### Calendar

See [the maintained guide](en/links.md).

### Post permalinks

See [the maintained guide](en/links.md).

## Multilingual Sites

See [the maintained guide](en/compatibility.md).

## WordPress Hooks

See [the maintained guide](en/developers/hooks.md).

### `persian_kit_loaded`

See [the maintained guide](en/developers/hooks.md).

### `persian_kit_date_display`

See [the maintained guide](en/developers/hooks.md).

### `persian_kit_gregorian_date`

See [the maintained guide](en/developers/hooks.md).

### `persian_kit_gregorian_date_display`

See [the maintained guide](en/developers/hooks.md).

### `persian_kit_digit_conversion`

See [the maintained guide](en/developers/hooks.md).

### `persian_kit_char_normalization`

See [the maintained guide](en/developers/hooks.md).

### `persian_kit_should_normalize`

See [the maintained guide](en/developers/hooks.md).

### `persian_kit_utilities`

See [the maintained guide](en/developers/hooks.md).

### `persian_kit_jalali_archives`

See [the maintained guide](en/developers/hooks.md).

### `persian_kit_jalali_permalinks`

See [the maintained guide](en/developers/hooks.md).

### `persian_kit_multilingual`

See [the maintained guide](en/developers/hooks.md).

### `persian_kit_is_persian_locale`

See [the maintained guide](en/developers/hooks.md).

### `persian_kit_reads_jalali`

See [the maintained guide](en/developers/hooks.md).

### `persian_kit_calendar_names`

See [the maintained guide](en/developers/hooks.md).

### `persian_kit_current_locale`

See [the maintained guide](en/developers/hooks.md).

### `persian_kit_content_locale`

See [the maintained guide](en/developers/hooks.md).

### `persian_kit_woocommerce_validate`

See [the maintained guide](en/developers/hooks.md).

### `persian_kit_woocommerce_cities`

See [the maintained guide](en/developers/hooks.md).

### `persian_kit_analytics_jalali_intervals`

See [the maintained guide](en/developers/hooks.md).

### `persian_kit_schema_rial_prices`

See [the maintained guide](en/developers/hooks.md).

### `persian_kit_email_font_family`

See [the maintained guide](en/developers/hooks.md).

### `persian_kit_acf_jalali_value`

See [the maintained guide](en/developers/hooks.md).

### `persian_kit_conflict_policies`

See [the maintained guide](en/developers/hooks.md).

### `persian_kit_import_sources`

See [the maintained guide](en/developers/hooks.md).

### `persian_kit_import_time_budget`

See [the maintained guide](en/developers/hooks.md).

## WooCommerce Checkout

See [the maintained guide](en/woocommerce.md).

### National ID

See [the maintained guide](en/woocommerce.md).

## WooCommerce Prices

See [the maintained guide](en/woocommerce.md).

### Call for price

See [the maintained guide](en/woocommerce.md).

## WooCommerce Emails

See [the maintained guide](en/woocommerce.md).

### Persian font

See [the maintained guide](en/woocommerce.md).

## WooCommerce Analytics

See [the maintained guide](en/woocommerce.md).

## Integrations

See [the maintained guide](en/forms.md).

### SEO plugins

See [the maintained guide](en/forms.md).

### Turning an integration off

See [the maintained guide](en/forms.md).

### Writing an integration

See [the maintained guide](en/forms.md).

## Forms

See [the maintained guide](en/forms.md).

### Contact Form 7

See [the maintained guide](en/forms.md).

### ACF

See [the maintained guide](en/forms.md).

### Forminator

See [the maintained guide](en/forms.md).

### Gravity Forms

See [the maintained guide](en/forms.md).

### WPForms

See [the maintained guide](en/forms.md).

## Switching from Another Plugin

See [the maintained guide](en/switching.md).

## WP-CLI

See [the maintained guide](en/maintenance.md).

## Module Keys

See [the maintained guide](en/settings.md).
