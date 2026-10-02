# Persian Kit

Persian Kit is a modular WordPress plugin for Persian-language sites. It focuses on safe Jalali date display, digit conversion, character normalization, Persian editor tooling, admin typography, and developer-facing PHP utilities.

Current release: `1.0.0-beta.4` (beta)

## What It Includes

- Jalali date conversion at the display layer
- Jalali date archive pages (`/1405/07/`), a Jalali archive list and calendar, and optional Jalali dates in post permalinks
- REST API Jalali companion fields
- Persian digit conversion for content, dates, counts and WooCommerce prices (off until turned on)
- Search that matches both Arabic (ي ك) and Persian (ی ک) spellings
- Arabic-to-Persian character normalization on save (off until turned on), with a batch tool for existing content
- Persian (fa_IR) admin interface, bundled until a WordPress.org language pack exists
- Vazirmatn-powered admin font support
- ZWNJ editor shortcuts for Classic Editor and Gutenberg
- Persian slug generation
- PHP validation and formatting helpers for common Iranian data
- WooCommerce Jalali date support for supported screens
- A Jalali date picker for Contact Form 7 and ACF date fields, WooCommerce's admin date fields and the post date in the classic editor and Quick Edit, and Contact Form 7 fields for Iranian numbers

## Requirements

- PHP `8.1+`
- WordPress `6.5+`

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
