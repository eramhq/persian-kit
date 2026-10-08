---
title: "Settings reference"
description: "Exact module labels, defaults and dependencies for a fresh installation."
---
# Settings reference

Open **Persian Kit** (requires `manage_options`). Use the module/card switch to enable or disable a module, change its options, then **Save changes**. One form saves the visible module tabs together; unavailable/omitted modules keep stored settings. Defaults below are for a fresh install, not an upgrade. `true` means on, `false` off, `[]` empty and `""` blank. Option keys are for developers; labels are the actual UI strings.

## Persian digits

Location: Display / Persian digits. `digit_conversion`

| Key | UI label | Default |
| --- | --- | --- |
| `enabled` | Persian digits | `false` |
| `dates` | Jalali dates | `true` |
| `numbers` | Post and comment counts | `true` |
| `prices` | Shop prices | `true` |
| `emails` | WooCommerce emails | `false` |
## Jalali dates

Location: Display / Jalali dates. `date_conversion`

| Key | UI label | Default |
| --- | --- | --- |
| `enabled` | Jalali dates | `true` |
| `global_conversion` | Convert every date (advanced) | `false` |
| `jalali_archives` | Jalali archives and calendar | `true` |
| `jalali_permalinks` | Jalali dates in post links | `false` |
| `gregorian_date` | Show the Gregorian date too | `false` |
| `gregorian_style` | Gregorian date | `"numeric"` |
| `gregorian_order` | Order | `"jalali_first"` |
| `gregorian_separator` | Between the dates | `"parentheses"` |
| `month_names` | Month names | `"auto"` |
| `month_names_by_locale` | Month names in %s | `[]` |
## Persian ی and ک

Location: Writing / Persian ی and ک. `char_normalization`

| Key | UI label | Default |
| --- | --- | --- |
| `enabled` | Persian ی and ک | `true` |
| `normalize_on_save` | Fix letters on save | `false` |
| `teh_marbuta` | Also replace ة with ه | `false` |
| `half_space_fix` | Add half-spaces on save | `false` |
## Admin font

Location: Display / Admin font. `admin_font`

| Key | UI label | Default |
| --- | --- | --- |
| `enabled` | Admin font | `true` |
| `font` | Font | `"vazirmatn"` |
## Half-space key

Location: Writing / Half-space key. `zwnj_editor`

| Key | UI label | Default |
| --- | --- | --- |
| `enabled` | Half-space key | `true` |
## WooCommerce

Location: WooCommerce / WooCommerce. `woocommerce`

Requires active: WooCommerce 9.9+.

| Key | UI label | Default |
| --- | --- | --- |
| `enabled` | WooCommerce | `true` |
| `checkout_normalize` | Fix what customers type | `true` |
| `checkout_validate` | Check phone numbers and postcodes | `true` |
| `national_id` | National ID | `"off"` |
| `city_select` | City list for Iran | `false` |
| `allowed_states` | Provinces you deliver to | `[]` |
| `short_checkout` | Shorter checkout when nothing needs shipping | `false` |
| `dates_admin` | Jalali date picker in the shop admin | `true` |
| `dates_analytics` | Jalali dates in WooCommerce Analytics | `true` |
| `call_for_price` | Show a text instead of an empty price | `false` |
| `call_for_price_text` | Text on the product page | `""` |
| `call_for_price_list_text` | Text in the shop and other lists | `""` |
| `call_for_price_link` | Link | `""` |
| `email_font` | Persian font in emails | `true` |
## Contact Form 7

Location: Integrations / Contact Form 7. `cf7`

Requires active: Contact Form 7.

| Key | UI label | Default |
| --- | --- | --- |
| `enabled` | Contact Form 7 | `true` |
## ACF

Location: Integrations / ACF. `acf`

Requires active: ACF.

| Key | UI label | Default |
| --- | --- | --- |
| `enabled` | ACF | `true` |
## Forminator

Location: Integrations / Forminator. `forminator`

Requires active: Forminator 1.50+.

| Key | UI label | Default |
| --- | --- | --- |
| `enabled` | Forminator | `true` |
## Gravity Forms

Location: Integrations / Gravity Forms. `gravityforms`

Requires active: Gravity Forms 2.9+.

| Key | UI label | Default |
| --- | --- | --- |
| `enabled` | Gravity Forms | `true` |
## WPForms

Location: Integrations / WPForms. `wpforms`

Requires active: WPForms 1.9.1+.

| Key | UI label | Default |
| --- | --- | --- |
| `enabled` | WPForms | `true` |
## Yoast SEO

Location: Integrations / Yoast SEO. `yoast`

Requires active: Yoast SEO.

| Key | UI label | Default |
| --- | --- | --- |
| `enabled` | Yoast SEO | `true` |
## Rank Math SEO

Location: Integrations / Rank Math SEO. `rank_math`

Requires active: Rank Math SEO.

| Key | UI label | Default |
| --- | --- | --- |
| `enabled` | Rank Math SEO | `true` |
## WPML

Location: Integrations / WPML. `wpml`

Requires active: WPML.

| Key | UI label | Default |
| --- | --- | --- |
| `enabled` | WPML | `true` |
## Polylang

Location: Integrations / Polylang. `polylang`

Requires active: Polylang.

| Key | UI label | Default |
| --- | --- | --- |
| `enabled` | Polylang | `true` |
## Persian slugs

Location: Writing / Persian slugs. `utilities`

| Key | UI label | Default |
| --- | --- | --- |
| `enabled` | Persian slugs | `true` |
| `persian_slugs` | Use Persian slugs | `true` |

## Choices and dependencies

- Month-name values: `auto`, `iranian`, `dari`, `pashto`, `kurdish`; per-language map keys are locales. Gregorian style: `numeric`/`named`; order: `jalali_first`/`gregorian_first`; separator: `parentheses`/`slash`/`dash`.
- Fonts: `vazirmatn`, `noto-sans-arabic`, `ibm-plex-sans-arabic`. National ID: `off`, `optional`, `required`. Empty provinces means all. Empty call-for-price product text uses the localized default, list text inherits it, and empty link adds no link.
- A module's child switches take effect only in the contexts it supports. Digits suboptions require its main switch. Form integrations control pickers independently of core date display; WooCommerce's dates have their own controls. Currency registration/schema protection and custom-field fallbacks can remain when an integration is off. See the guides before deactivating the plugin itself.
- Tools have no module settings of their own. The normalization UI depends on the enabled character module's REST routes; its CLI works even with that module off. Settings live in `persian_kit_settings`, sanitized by the registered Settings API.

For examples and limitations: [text](text.md), [dates](dates.md), [links](links.md), [WooCommerce](woocommerce.md), [forms](forms.md), [maintenance](maintenance.md) and [compatibility](compatibility.md).
