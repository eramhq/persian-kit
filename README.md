# Persian Kit

[فارسی](README.fa.md)

Persian Kit brings Jalali dates, Persian writing tools and optional WooCommerce/form integrations to WordPress. This source tree is version **1.0.1**.

## Requirements

WordPress **6.8+**, PHP **8.1+** with `mbstring`; `intl` for correct Persian sorting. WooCommerce **9.9+** only for shop integration. See [installation](docs/en/installation.md) and [compatibility](docs/en/compatibility.md).

## Installation

Upload a built `persian-kit.zip` through WordPress Plugins, install and activate. A source checkout needs:

```bash
composer install
npm ci
npm run build
```

`npm run dist` builds the ZIP; see its [prerequisites](docs/en/installation.md).

## Quick start

Open **Persian Kit > Display**. Jalali dates start on; Persian digits start off. Turn on **Persian digits** and save to display `شماره 123` as `شماره ۱۲۳` in supported content without rewriting it. In **Writing**, keep **Fix letters on save** off unless you want saved text changed. Follow the [first-use walkthrough](docs/en/overview.md).

## Documentation

- [English guides](docs/en/overview.md) · [راهنمای فارسی](docs/fa/overview.md)
- [Settings](docs/en/settings.md) · [Developer API](docs/en/developers/api.md)
- [Development](docs/DEVELOPMENT.md) · [Documentation maintenance](docs/README.md)
- [Changelog](CHANGELOG.md) · [License: GPL-2.0-or-later](LICENSE)
