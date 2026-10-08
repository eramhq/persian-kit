---
title: "WooCommerce"
description: "Configure Iranian checkout fields, prices, emails and Jalali reports."
---
# WooCommerce

## Checkout and addresses

Open **Persian Kit > WooCommerce > Checkout and addresses** with WooCommerce 9.9+ active. The module, **Fix what customers type** and **Check phone numbers and postcodes** start on. Save changes after selecting options; see [all defaults](settings.md).

Input normalization changes what is saved: Persian/Arabic digits in phone/postcode become English; postcode spaces/dashes are removed; Arabic ي/ك in names, company, address and city become Persian. This applies across countries in classic checkout, block checkout/Store API and My Account addresses. For an Iranian address, phone and postcode validation checks their Iranian format; phone accepts mobile or landline. Empty fields use WooCommerce's own required checks. Example: `۰۹۱۲۱۲۳۴۵۶۷` becomes `09121234567`. Persian-digit order numbers are also accepted by order tracking and admin order search.

Optional features:

- **National ID**: Off (default), Optional or Required. Required applies to customers in **every country**, not just Iran. Valid values save in English digits on order/customer meta `_wc_other/persian-kit/national-id`. Existing order IDs remain visible while the field option is off and the integration remains on.
- **City list for Iran**: off. Suggests province cities but accepts villages/unlisted places. A uniquely matched alternative spelling is saved as the listed spelling; unmatched/ambiguous names stay as typed. The bundled list has 1,454 cities in 31 provinces, from the 1403 country-divisions data. The classic cart shipping calculator also gets suggestions; the cart block has no address form. The [cities filter](developers/hooks.md) can edit the list.
- **Provinces you deliver to**: an empty list means all. Selecting provinces limits Iranian billing/shipping choices on the storefront, not admin/cron/general REST. Selecting “Only these provinces” with none checked also means all, not no deliveries. Existing addresses keep readable province names.
- **Shorter checkout when nothing needs shipping**: off. A nonempty cart whose every item is virtual asks for name, country, phone, email and enabled national ID; notes remain. A physical item retains the full address even if store shipping is disabled. Orders omit the hidden address fields; a signed-in customer's existing address is preserved. Test tax/payment extensions that require address data.

## Prices and currency

In **Prices and currency**, enable **Show a text instead of an empty price** to show “Call for price” (Persian: `تماس بگیرید`). A price of `0` remains free. Set **Text on the product page**, **Text in the shop and other lists**, and optionally **Link** (phone or HTTP(S)/site-relative page). Empty list text uses the product text. Text is plain, limited to 100 characters. A link changes the product-page text and shop button; external products keep their own button. The older React All Products block can still show zero; use Product Collection and test your template.

WooCommerce's **Settings > General > Currency** lists `IRHT` (thousand toman, ×10,000 rials) and `IRHR` (thousand rial, ×1,000), alongside its own `IRT` and `IRR`. These currencies remain registered when the integration switch is off. **Changing the currency does not convert saved prices, shipping costs or coupons.** A value `120000` stays `120000` in the newly selected unit. Check your gateway and convert amounts separately before changing units; Persian Kit provides no payment gateway.

Structured-data amounts in `IRT`, `IRHT` and `IRHR` are converted to rials (`IRR`) in WooCommerce and supported SEO output, without changing shopper prices. This also runs with the WooCommerce module off. Other currencies remain unchanged. Feeds/accounting tools and gateways have their own currency support; it is not guaranteed here.

## Emails and dates

**Persian font in emails** starts on and replaces the default font for Persian emails with installed-device fonts, not a downloaded web font. Custom fonts retain priority. Supported block emails get RTL styling on RTL sites.

**Display > Persian digits > WooCommerce emails** starts off and requires the digits module. It converts displayed order numbers, prices, quantities and dates in supported bodies, subjects/headings and email-editor order tags. Phones, postcodes, national IDs, SKUs, coupon codes, links, attachments and SMS are not converted. Email language controls conversion. Third-party email renderers need testing.

**Jalali date picker in the shop admin** starts on for order/product/coupon dates and order month filters. **Jalali dates in WooCommerce Analytics** also starts on: Jalali presets, months, seasons, years and date displays apply on single-language sites; on multilingual sites they follow the admin’s reading language. Days use the site timezone; weeks use WordPress's first weekday. REST queries for these reports still use Gregorian boundaries but request Jalali aggregation periods. CSV exports add a Jalali date column; underlying stored dates stay Gregorian. This is broader than relabeling a Gregorian month. A custom reports endpoint may need the [interval opt-out filter](developers/hooks.md).

Shop/email date rendering is handled by the WooCommerce integration; do not assume switching off the separate core date module disables all its date behavior. For exact controls see [settings](settings.md). Before deactivation, read [currency and form removal precautions](compatibility.md).
