---
title: "Jalali dates"
description: "Understand date display, date input, timezones and REST companion fields."
---
# Jalali dates

## Dates that visitors read

**Persian Kit > Display > Jalali dates** is on by default. WordPress stores Gregorian dates; the module changes supported post/comment dates, admin dates and date UI. It does not convert database date columns to Jalali. Set the site's timezone in **Settings > General**, not the server or browser clock.

With date format `Y/m/d`, Gregorian `2026-10-02` displays as `1405/07/10`. Persian digits require the separate digits module and its **Jalali dates** option. A timestamp represents an instant; a date stored without a timezone must be interpreted in its proper context. The implementation reads WordPress GMT fields in UTC and local fields in the site timezone, avoiding adding the offset twice. A late-night UTC instant can correctly fall on the next local day.

The Classic Editor's post date and Quick Edit use a Jalali picker. The block editor adds a Jalali **Publish** row, including pre/post-publish panels; **Now** uses the site time. Admin post/media month filters use Jalali ranges. A chosen date is saved as Gregorian. JavaScript is needed for the picker UI; it is not a promise that every third-party date input accepts Jalali.

## Formats and month names

Use WordPress date-format tokens: `Y/m/d` for numeric dates or `j F Y` for day, month name and year. **Month names** defaults to Automatic: Iranian, Dari for `fa_AF`, Pashto for `ps`, Sorani Kurdish for `ckb`. Explicit choices change names, not calendar arithmetic. On multilingual sites the UI offers one choice per language. Browser picker names use `Intl`; missing Pashto/Sorani data falls back to Dari/Iranian names respectively.

**Show the Gregorian date too** is off. When enabled, choose **Gregorian date** (Numbers or Month names), **Order**, and **Between the dates** (parentheses, slash or dash). For `j F Y`, the default arrangement for 2 October 2026 reads `10 مهر 1405 (2026-10-02)` with digit conversion off. It applies to frontend post/comment dates containing day, month and year, not times, archives, admin, mail, REST or `persian_kit_date()`. Numeric `Y-m-d` and `Ymd` are excluded unless selected as the site's date format. Numeric companions include invisible direction marks for RTL display.

**Convert every date (advanced)** is off. It additionally filters `wp_date()` output and can affect plugins that parse formatted dates. Enable only after testing your theme, SEO, booking and form workflows. Machine formats and feeds have guards, but an arbitrary date-consuming plugin is not guaranteed safe.

## REST and developer use

While this module is enabled, post types exposed in REST get read-only `date_jalali` and `date_modified_jalali` fields, in view/embed contexts. They use English digits and site time in `YYYY-MM-DDTHH:MM:SS` shape, or `null` for unavailable dates. They are **not ISO 8601 Gregorian dates** despite their shape. Original REST fields remain Gregorian; this is not a conversion of every endpoint. Requests selecting only these fields with `_fields` are supported.

Use [date helpers](developers/api.md) for explicit formatting and calendar conversion. Pass a Unix timestamp and explicit `DateTimeZone` for deterministic formatting; helpers treat `0` and `''` as now, and date strings go through PHP `strtotime()`. Strings without zones follow PHP's default timezone (normally UTC in WordPress).

If a theme still shows Gregorian dates, check the format, language and whether it uses supported WordPress date functions before enabling the advanced switch. Check date pickers after cache/asset optimization changes. Related: [archives and links](links.md), [forms](forms.md), [WooCommerce](woocommerce.md), [compatibility](compatibility.md).
