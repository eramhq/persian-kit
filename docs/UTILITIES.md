# Utilities Guide

This guide is for developers integrating Persian Kit utilities into themes, plugins, form handlers, REST endpoints, and admin tools.

## When To Use These Helpers

Use the utility layer when you need one of these behaviors in your own code:

- Normalize user-entered Persian text before storage or comparison
- Validate Iranian identifiers, bank details, postal codes, licence plates and bill IDs
- Generate Persian-safe slugs
- Render Persian numbers, currency amounts or relative-time strings
- Fix half-spaces and text typed with the wrong keyboard layout
- Sort lists in Persian alphabetical order
- Detect whether text is Persian or Arabic

These helpers are most useful when your own code handles user input outside the plugin's built-in WordPress hooks.

## Integration Patterns

### Validate and return field-level errors

```php
$result = persian_kit_validate_phone($_POST['mobile'] ?? '');

if (!$result->isValid()) {
    return new WP_Error(
        'invalid_mobile',
        implode(' ', $result->errors()),
        ['status' => 422]
    );
}

$mobile = $result->detail()->normalizedE164;
```

Use this pattern for:

- REST API endpoints
- AJAX actions
- Custom settings pages
- Form plugin adapters

### Normalize before lookup or persistence

```php
$normalizedName = persian_kit_normalize_persian($rawName);
$normalizedSlug = persian_kit_slug($postTitle);
```

This is useful when:

- you compare user-entered strings
- you store canonical searchable values
- you need predictable slugs from Persian titles

### Keep validation and formatting separate

Prefer:

```php
$result = persian_kit_validate_card_number($input);

if ($result->isValid()) {
    $bank = $result->detail()?->bank; // null when the BIN isn't listed
}
```

Do not infer validity from formatter output alone.

## Utility Behavior by Category

### Validators

All validators return abzar's `ValidationResult` instead of throwing for invalid user input, so they are safe to use directly in request handlers.

Current validators:

- `persian_kit_validate_national_id`
- `persian_kit_validate_legal_id`
- `persian_kit_validate_phone`
- `persian_kit_validate_card_number`
- `persian_kit_validate_iban`
- `persian_kit_validate_postal_code`
- `persian_kit_validate_plate_number`
- `persian_kit_validate_bill_id`

#### Details

`$result->detail()` returns an object with read-only properties, or `null`. Call `jsonSerialize()` on it when you need an array.

| Helper | Detail properties |
| --- | --- |
| `persian_kit_validate_national_id` | `value`, `cityCode`, `city`, `province` |
| `persian_kit_validate_legal_id` | `value` |
| `persian_kit_validate_phone` | `type`, `normalizedLocal`, `normalizedE164`, `operator`, `areaCode`, `city`, `province` |
| `persian_kit_validate_card_number` | `value`, `bin`, `bank` |
| `persian_kit_validate_iban` | `value`, `bankCode`, `bank` |
| `persian_kit_validate_postal_code` | `postalCode`, `zoneCode` |
| `persian_kit_validate_plate_number` | `twoDigit`, `letter`, `threeDigit`, `cityCode`, `type`, `province`, `provinces` |
| `persian_kit_validate_bill_id` | `billId`, `paymentId`, `type` |

#### Warnings

Well-formed, checksum-valid input is valid even when a lookup fails: an unlisted national-ID city prefix, IBAN bank code or landline area code. The result then carries a warning (`warnings()`, `warningCodes()`) and the lookup property is `null`. Use `isStrictlyValid()` when you need every lookup to resolve.

### Formatters

These helpers are stricter and throw abzar's `FormatException` when the input is malformed:

- `persian_kit_number_format`
- `persian_kit_number_to_words`
- `persian_kit_time_ago`
- `persian_kit_ordinal_word`
- `persian_kit_ordinal_short`

`FormatException` extends `\RuntimeException`. Wrap these helpers when the input is user-controlled:

```php
try {
    $formatted = persian_kit_number_format($userValue, '٬');
} catch (\RuntimeException $e) {
    $formatted = null;
}
```

`persian_kit_words_to_number` doesn't throw; it returns `null` for text that isn't a number. The currency helpers throw `InvalidArgumentException` for a unit other than `toman` or `rial`.

### Text helpers

`persian_kit_normalize_persian` and `persian_kit_slug` are safe building blocks for text pipelines.

Typical flow:

1. Normalize text
2. Validate or compare
3. Store canonical form
4. Format for display separately if needed

## Common Recipes

### Guard calls when Persian Kit may be inactive

The helpers exist only while Persian Kit is active. They are defined as soon as the plugin loads, before `persian_kit_loaded` fires, and they work even when the Utilities module is switched off (that switch only controls Persian slugs). A theme or plugin that should keep working without Persian Kit checks first and falls back:

```php
function mytheme_post_date(): string
{
    if (function_exists('persian_kit_date')) {
        return persian_kit_date('j F Y', get_post_timestamp());
    }

    return get_the_date('j F Y');
}
```

Code that needs Persian Kit's services (not just the helpers) can wait for the plugin:

```php
add_action('persian_kit_loaded', function () {
    // Persian Kit is active and its services are registered.
});
```

### Accept Persian digits in a custom checkout field

```php
$result = persian_kit_validate_phone($_POST['billing_mobile'] ?? '');

if (!$result->isValid()) {
    wc_add_notice(implode(' ', $result->errors()), 'error');
} else {
    $_POST['billing_mobile'] = $result->detail()->normalizedLocal;
}
```

### Build searchable normalized meta

```php
$searchable = persian_kit_normalize_persian($rawText);
$searchable = persian_kit_to_english_digits($searchable);

update_post_meta($postId, '_searchable_value', $searchable);
```

### Generate a Persian-preserving slug outside the post editor

```php
$slug = persian_kit_slug($label); // a ZWNJ becomes "-"
```

### Show a price in toman

```php
echo esc_html(persian_kit_currency_format($priceInToman)); // ۱،۵۰۰،۰۰۰ تومان
```

### Sort terms by their Persian names

```php
$sorted = persian_kit_persian_sort($terms, static fn (WP_Term $term): string => $term->name);
```

This needs the `intl` extension for correct Persian order; without it the sort falls back to byte order.

### Render a relative timestamp in Persian

```php
echo esc_html(persian_kit_time_ago(get_post_timestamp($post)));
```

## Design Constraints To Keep In Mind

### Validation strings are Persian

Error and warning messages are Persian strings. For machine-readable results use `errorCodes()` and `warningCodes()`, which return abzar `ErrorCode` enum cases.

### `persian_kit_normalize_persian` is intentionally narrower than full content normalization

It normalizes text input, not rich HTML documents. For content-wide HTML-aware normalization, use the plugin's module behavior instead of the helper.

### `persian_kit_is_arabic` is not a general Unicode Arabic detector

It is designed to distinguish Arabic-exclusive character usage from Persian usage. Use it as a heuristic aligned with Persian Kit's rules, not as a universal language detector.

### Date helpers and digit helpers are separate on purpose

`persian_kit_date()` returns date text, but not necessarily Persian digits. Compose helpers when you need both:

```php
echo persian_kit_to_persian_digits(persian_kit_date('Y/m/d'));
```

## Suggested Conventions for Integrators

- Normalize before validating when you own the storage format.
- Store canonical machine-friendly values, then format for output later.
- Treat `ValidationResult` as the boundary type for user-input validation.
- Avoid persisting formatted output like `1,234,567` when raw numeric values are available.
- Prefer explicit helper calls in your own code rather than depending on global display hooks.

## Related Docs

- [Reference](REFERENCE.md)
- [Development Guide](DEVELOPMENT.md)
