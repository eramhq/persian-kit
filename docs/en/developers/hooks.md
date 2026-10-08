---
title: "Actions and filters"
description: "Public hook names, arguments, timing and return behavior."
---
# Actions and filters

Add filters in a plugin, the theme's `functions.php`, or `persian_kit_loaded`, before modules boot. Boot-time gates do not change already-registered hooks later in the request. Return the filtered value and set the accepted-argument count when reading more than one argument. These hooks do not grant permissions or validate a custom endpoint for you.

## persian_kit_loaded

`action` — `—`

Services registered on plugins_loaded; modules boot on after_setup_theme at priority 20.

## persian_kit_date_display

`filter` — `string $date, string $format, int $timestamp, DateTimeZone $timezone`

Jalali formatter result; return the date string.

## persian_kit_gregorian_date

`filter` — `bool $show, string $format, DateTimeInterface $date`

Return false to suppress a Gregorian companion for this frontend date.

## persian_kit_gregorian_date_display

`filter` — `string $date, string $format, int $timestamp, DateTimeZone $timezone`

Gregorian companion before joining; empty string omits it.

## persian_kit_digit_conversion

`filter` — `bool $enabled, string $hook`

Gate each digit hook, including woocommerce_emails and woocommerce_email_order_number.

## persian_kit_char_normalization

`filter` — `bool $enabled, string $hook`

Gate wp_insert_post_data, preprocess_comment, pre_term_name, pre_term_description or posts_search.

## persian_kit_should_normalize

`filter` — `bool $normalize, object $postContext, array $data, array $postarr`

Per-post save normalization after hard exclusions; not a batch-job gate.

## persian_kit_utilities

`filter` — `bool $enabled, string $feature`

Gate sanitize_title or encode_links.

## persian_kit_jalali_archives

`filter` — `bool $enabled`

False disables Jalali archive/calendar rendering, not archive URL recognition.

## persian_kit_jalali_permalinks

`filter` — `bool $enabled`

False keeps Gregorian generated post links; old links still resolve.

## persian_kit_multilingual

`filter` — `bool $multilingual`

Opt into/out of locale-aware behavior; automatic with configured WPML/Polylang.

## persian_kit_is_persian_locale

`filter` — `bool $persian, string $locale`

Default fa/fa_*; controls Persian writing identity.

## persian_kit_reads_jalali

`filter` — `bool $reads, string $locale`

Jalali/digit display language on multilingual sites, distinct from writing.

## persian_kit_calendar_names

`filter` — `string $set, string $locale`

Return iranian, dari, pashto or kurdish; cached per locale in the request.

## persian_kit_current_locale

`filter` — `string $locale`

Reading locale for the current page/email/admin request.

## persian_kit_content_locale

`filter` — `?string $locale, string $objectType, int $objectId`

post/term content locale; ID 0 is the object being saved; null is unknown and treated as Persian for writing.

## persian_kit_woocommerce_validate

`filter` — `bool $validate, string $rule, string $group, string $value`

Rules phone/postcode/national_id; group billing/shipping (national_id is billing).

## persian_kit_woocommerce_cities

`filter` — `array $cities`

Province code to list of names, e.g. THR; filters suggestions and canonical saved names once per request.

## persian_kit_analytics_jalali_intervals

`filter` — `bool $enabled, string $route, WP_REST_Request $request`

Opt a requesting wc-analytics stats route out of Jalali periods.

## persian_kit_schema_rial_prices

`filter` — `bool $enabled, string $currency`

False keeps the node currency instead of converting to IRR.

## persian_kit_email_font_family

`filter` — `string $stack`

Installed-font CSS stack; values with ; { } < > are rejected.

## persian_kit_acf_jalali_value

`filter` — `bool $jalali, array $field`

False keeps this formatted ACF value Gregorian.

## persian_kit_conflict_policies

`filter` — `array $policies`

Compatibility guidance by plugin file; name/type/summary/handles/recommendations/note, optional import and active_when.

## persian_kit_import_sources

`filter` — `array $sources`

Objects implementing Service\Import\Source; AbstractSource supplies a base; lowercase source keys.

## persian_kit_import_time_budget

`filter` — `int|float $seconds`

Default 8 seconds, minimum 1; each import request/batch.

## Example

Keep titles' digits as typed while allowing other enabled digit filters:

```php
add_filter('persian_kit_digit_conversion', function (bool $enabled, string $hook): bool {
    return $hook === 'the_title' ? false : $enabled;
}, 10, 2);
```

The target must already be enabled in settings; filters that return true do not force a disabled module to boot. See [settings](../settings.md), [API](api.md), [recipes](recipes.md) and [source contracts](../../../src/Contracts/ModuleInterface.php). Internal service classes have no separate compatibility promise.

## Module and import extension contracts

For repository contributors, [ModuleInterface](../../../src/Contracts/ModuleInterface.php) defines `category()` (`forms`, `commerce`, `compat`, or `null`), `requiredPlugins()` (name/check, optional version/minVersion/slug/url), `isAvailable()` and `unavailableReason()`. `boot()` runs when available and enabled; `bootDisabled()` registers fallbacks when available but disabled. `formsUsingFields()` supplies the affected-form warning. Built-in modules are listed in [ModuleRegistry](../../../src/Core/ModuleRegistry.php); there is no public module-registration filter. [IranianFieldTypes](../../../src/Modules/Forms/IranianFieldTypes.php) centralizes field validation and normalized values.

An import extension implements [Source](../../../src/Service/Import/Source.php) and supplies [Task](../../../src/Service/Import/Task.php) objects, then registers through `persian_kit_import_sources`. Review availability, permission handling, logging and conditional undo rather than treating arbitrary SQL as an import task. These interfaces document the current implementation, without a separate stability guarantee.
