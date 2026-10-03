# Persian Kit

Persian Kit is a modular WordPress plugin for Persian-language sites. It focuses on safe Jalali date display, digit conversion, character normalization, Persian editor tooling, admin typography, and developer-facing PHP utilities.

Current release: `1.0.0-beta.6` (beta)

## What It Includes

- Jalali date conversion at the display layer
- Jalali date archive pages (`/1405/07/`), a Jalali archive list and calendar, and optional Jalali dates in post permalinks
- REST API Jalali companion fields
- Persian digit conversion for content, dates, counts and WooCommerce prices (off until turned on)
- Search that matches both Arabic (ي ك) and Persian (ی ک) spellings
- Arabic-to-Persian character normalization on save (off until turned on), with a batch tool for existing content
- Persian (fa_IR) admin interface, bundled until a WordPress.org language pack exists
- A Persian admin font: Vazirmatn, Noto Sans Arabic or IBM Plex Sans Arabic
- ZWNJ editor shortcuts for Classic Editor and Gutenberg
- Persian slug generation
- PHP validation and formatting helpers for common Iranian data
- WooCommerce: Jalali dates, a checkout for Iran (fixed digits, checked phone numbers and postcodes, national ID, cities, provinces you deliver to), thousand toman and thousand rial, and Persian digits in emails
- A Jalali date picker for Contact Form 7, ACF and Forminator date fields, WooCommerce's admin date fields and the post date in the classic editor and Quick Edit
- Iranian field checks (mobile, national ID, postcode, card, IBAN) for Contact Form 7 and Forminator
- Compatibility with Yoast SEO, Rank Math, WPML and Polylang
- WooCommerce: "call for price" for products with no price, a shorter checkout when nothing needs shipping, and a Persian font in emails
- A guided switch from Parsi Date, Persian WooCommerce and Persian WooCommerce Shipping, with a review first, a report and undo

## Requirements

- PHP `8.1+`
- WordPress `6.8+`
- WooCommerce `9.9+`, for the WooCommerce features

## Installation

### Production

1. Build the plugin assets.
2. Create a distributable zip.
3. Install the zip in WordPress as a normal plugin.

### Development

```bash
composer install
npm ci
npm run build
```

Then activate the plugin from a local WordPress site.

## Development Commands

```bash
composer test
composer test:integration
composer phpstan
composer phpcs
npm run test:js
npm run build
npm run build:pot
npm run build:i18n   # compile languages/*.po into .mo, .l10n.php and script JSON
npm run dist
```

## Project Docs

- [Changelog](CHANGELOG.md)
- [Development Guide](docs/DEVELOPMENT.md)
- [Reference](docs/REFERENCE.md)
- [Utilities Guide](docs/UTILITIES.md)

## License

GPL-2.0-or-later
