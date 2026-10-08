---
title: "Forms and date fields"
description: "Use Iranian validation and Jalali inputs in supported form plugins."
---
# Forms and date fields

## Enable an integration

Open **Persian Kit > Integrations > Forms**. Each integration defaults on but requires its plugin. Its switch controls its picker and validation features; the separate **Jalali dates** switch controls formatted dates in supported CF7 emails, ACF values, Forminator notifications and Gravity Forms entries/emails. Do not use the display switch as a way to stop input conversion. Language rules apply on multilingual sites.

These integrations submit Gregorian date values while people choose Jalali dates; WPForms also stores a display value as explained below. Example: `1405/07/10` corresponds to `2026-10-02`; the receiving plugin's own date format still controls storage. Iranian identifiers are validated, not verified against government/bank ownership records.

## Contact Form 7

Use the form editor's Iranian tag buttons (CF7 6.0+) or enter form tags:

```text
[mobile_ir* mobile]
[national_id national-code]
[postcode_ir postcode]
[card_ir card]
[iban_ir iban]
[date appointment]
```

`*` makes a field required. A mobile value `+989121234567` becomes `09121234567`; landlines fail the mobile-only check. Other types return a 10-digit national ID, 10-digit postcode, 16-digit card, or `IR` plus 24 IBAN digits when valid. Add the matching mail tag in CF7's Mail tab. Date fields use the Jalali picker but submit Gregorian dates. Use `[date appointment gregorian]` to keep CF7’s native input. In mail, `[_raw_appointment]` or `[_format_appointment "Y-m-d"]` keeps Gregorian output; the ordinary mail tag follows the display setting. Persian digits in tel/number/range/date fields normalize before validation. Check messages in the Messages tab. These are CF7 form tags, not new WordPress shortcodes.

## ACF

Date Picker and Date Time Picker fields get the picker on edit screens, ACF blocks and `acf_form()`. Stored values remain `Ymd` or `Y-m-d H:i:s`. With Jalali date display on, a return format `Y/m/d` makes `get_field()` display `1405/07/10` for 2 October 2026. Machine formats (`Ymd`, `Y-m-d`, `Y-m-d H:i:s`, `U`, `c`, etc.), REST and unformatted `get_field('name', $post_id, false)` stay Gregorian. Do not parse the formatted display value for storage; read the unformatted value or opt out with `persian_kit_acf_jalali_value`. ACF 4 fields with `save_format` retain ACF's own picker.

## Forminator 1.50+

Use a Date field in **Calendar** style. Number boxes and dropdowns stay Gregorian. Add `persian-kit-gregorian` to **Additional CSS Classes** to keep its native calendar. Start/end and past-date limits are supported; disabled weekdays/dates/ranges and limits from other fields are checked on submission but not all greyed out in the picker. Notifications/Submissions can show Jalali; exports, webhooks, saved data and redirect URLs retain Gregorian values.

For Iranian validation, add one class under **Styling > Additional CSS Classes** to a Text field:

| Class | Result after valid submission |
| --- | --- |
| `persian-kit-mobile` | Local mobile, e.g. `09121234567` |
| `persian-kit-national-id` | 10 digits |
| `persian-kit-postcode` | 10 digits |
| `persian-kit-card` | 16 digits |
| `persian-kit-iban` | `IR` + 24 digits |

Classes are recognized on Text, Phone and Number fields; use Text for leading zeros. The first Iranian class wins. Phone/Number/Currency inputs normalize Persian digits; browser `type=number` can discard Persian input before JavaScript sees it, so use a separator/masked input or Text. Polls and quizzes are not covered.

## Gravity Forms 2.9+

The **Iranian fields** group in Add Fields supplies mobile, national ID, postcode, card and IBAN fields. For example, add Mobile number and submit Persian digits for `09121234567`; the entry stores its normalized local form. Date Picker, Date Field and Date Drop Down accept Jalali; tick **Gregorian calendar** under Date Format to opt out. Dates save Gregorian, including edits to entries. Display merge tags can be Jalali; `:raw`, `:urlencode`, redirects and exports stay Gregorian. Phone, number, quantity, time, date, postcode and customer-entered price fields normalize input digits; choice values are preserved. The Address field gets an Iran type and currency choices include toman/rial.

## WPForms 1.9.1+, including Lite

Use **Iranian fields** in Add Fields for the same five identifier types or the separate Jalali date field. The custom date type is `persian-kit-date`; it submits Gregorian input and stores `date` as Gregorian `Y-m-d`, `unix` at midnight UTC, and `value` as the formatted display date (Jalali on a Jalali-reading page). Emails use that display value. Number/price inputs normalize Persian digits. WPForms' paid Date / Time, Phone and Address fields are not covered by this integration. Test the custom date field rather than assuming a paid field is replaced.

## Turning features off

The settings cards list forms using custom fields. With only an integration switch off, CF7, Gravity Forms and WPForms custom fields fall back to unchecked text inputs; WPForms' Jalali date field does too. Gravity Forms keeps Iran address/currency definitions. Forminator fields become ordinary fields without Persian Kit checks.

Deactivating **Persian Kit itself** removes these fallbacks: CF7 can print unknown tags as text, Gravity Forms can show a field without an input and WPForms can omit custom fields. Replace fields before deactivation. Turning off display does not convert old entries or undo normalization. Related: [settings](settings.md), [dates](dates.md), [compatibility](compatibility.md), [custom date inputs](developers/api.md).
