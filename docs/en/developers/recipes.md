---
title: "Developer recipes"
description: "Use helpers safely in theme templates and custom WordPress handlers."
---
# Developer recipes

These are contextual WordPress snippets, not standalone scripts. Run them only where their variables and authorization are supplied. Pure deterministic examples are in the [API](api.md); validation results and exceptions have their main reference there.

## Guard calls when Persian Kit may be inactive

In a theme template, fall back to WordPress. Do not register this sample function twice.

```php
function mytheme_post_date(): string
{
    $timestamp = get_post_timestamp();
    if ($timestamp !== false && function_exists('persian_kit_date')) {
        return persian_kit_date('j F Y', $timestamp);
    }
    return get_the_date('j F Y');
}
```

## Validate input before storage

Inside an authorized WordPress handler: check authentication, nonce and permissions separately. A phone validator accepts landlines too; it is not mobile-only.

```php
$raw = $_POST['phone'] ?? '';
$input = is_string($raw) ? sanitize_text_field(wp_unslash($raw)) : '';
$result = persian_kit_validate_phone($input);
if (!$result->isValid()) {
    return new WP_Error('invalid_phone', implode(' ', $result->errors()), ['status' => 422]);
}
$phone = $result->detail()->normalizedE164;
```

## Build searchable normalized meta

This writes one custom meta key; it is not the built-in search filter. Supply an authorized post ID and raw text.

```php
$searchable = persian_kit_to_english_digits(persian_kit_normalize_persian($rawText));
update_post_meta($postId, '_searchable_value', $searchable);
```

## Format output separately

Handle invalid numeric input and escape output. For amount 1500000, the default currency helper produces ۱،۵۰۰،۰۰۰ تومان.

```php
try {
    $formatted = persian_kit_number_format($userValue, '٬');
} catch (\RuntimeException $e) {
    $formatted = '';
}
echo esc_html($formatted);
echo esc_html(persian_kit_currency_format(1500000));
```

## Sort terms and show relative time

Requires WordPress terms/post context; intl gives correct Persian order. Relative text depends on the current time.

```php
$sorted = persian_kit_persian_sort($terms, static fn (WP_Term $term): string => $term->name);
echo esc_html(persian_kit_time_ago(get_post_timestamp($post)));
```

## Generate a slug or Persian date explicitly

These return strings; assigning a slug to a stored post is a separate write. Digit conversion can be composed with dates.

```php
$slug = persian_kit_slug('می‌خواهم بنویسم'); // می-خواهم-بنویسم
echo esc_html(persian_kit_to_persian_digits(persian_kit_date('Y/m/d')));
```

Related: [hooks](hooks.md), [API](api.md), [development](../../DEVELOPMENT.md).
