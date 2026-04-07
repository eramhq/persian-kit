# Persian Kit

Persian Kit is a modular WordPress plugin for Persian-language sites. It focuses on safe Jalali date display, digit conversion, character normalization, Persian editor tooling, admin typography, and developer-facing PHP utilities.

Current release line: `0.9.x`

## What It Includes

- Jalali date conversion at the display layer
- REST API Jalali companion fields
- Persian digit conversion for content areas
- Arabic-to-Persian character normalization
- Batch normalization tools for existing content
- Vazirmatn-powered admin font support
- ZWNJ editor shortcuts for Classic Editor and Gutenberg
- Persian slug generation
- PHP validation and formatting helpers for common Iranian data
- WooCommerce Jalali date support for supported screens

## Requirements

- PHP `8.1+`
- WordPress `6.2+`

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
npm run build
npm run dist
```

## Project Docs

- [Changelog](CHANGELOG.md)
- [Development Guide](docs/DEVELOPMENT.md)
- [Reference](docs/REFERENCE.md)
- [Utilities Guide](docs/UTILITIES.md)

## Status

`0.9.x` means the plugin is usable, but still in a hardening phase. API and behavior may still tighten before `1.0`.

## License

GPL-2.0-or-later
