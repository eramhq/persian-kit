# Persian Kit

[فارسی](README.fa.md)

Persian Kit brings Jalali dates, Persian writing tools and optional WooCommerce/form integrations to WordPress. This source tree is version **1.0.2**.

Made by [Eram](https://eram.dev/en/). Homepage and guides: [eram.dev/en/projects/persian-kit](https://eram.dev/en/projects/persian-kit/) and [eram.dev/en/docs/persian-kit](https://eram.dev/en/docs/persian-kit/overview/).

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

## Third-party software

Persian Kit ships these third-party components:

- [eram/abzar](https://eram.dev/fa/projects/abzar-php/): MIT-licensed PHP utilities for Persian text and digit handling.
- [eram/daynum](https://eram.dev/fa/projects/daynum/): MIT-licensed PHP Jalali date library.
- [Alpine.js](https://alpinejs.dev): MIT-licensed JavaScript framework, bundled into the admin script together with its MIT-licensed dependencies `@vue/reactivity` and `@vue/shared`.
- [Vazirmatn](https://github.com/rastikerdar/vazirmatn): Persian font by Saber Rastikerdar, licensed under the SIL Open Font License 1.1.
- [Noto Sans Arabic](https://github.com/notofonts/arabic): font by Google and the Noto Project Authors, licensed under the SIL Open Font License 1.1.
- [IBM Plex Sans Arabic](https://github.com/IBM/plex): font by IBM, licensed under the SIL Open Font License 1.1.
- [intl-datepicker](https://eram.dev/fa/projects/intl-datepicker/): MIT-licensed date picker Web Component, bundled into `public/js/datepicker.js`.
- [@internationalized/date](https://github.com/adobe/react-spectrum/tree/main/packages/@internationalized/date): Adobe's calendar library, licensed under the Apache License 2.0, bundled into `public/js/datepicker.js`.
- Tapin's list of provinces and cities, from [Persian WooCommerce Shipping](https://wordpress.org/plugins/persian-woocommerce-shipping/) (GPLv3), in `public/data/pws-tapin.json`. It is only used to read addresses that plugin saved with Tapin's ids.

Because `public/data/pws-tapin.json` is GPLv3, the plugin as distributed is under GPLv3. The plugin's own code stays GPLv2 or later.

All bundled components are GPL-compatible. Their license texts ship with the plugin: `packages/eram/*/LICENSE`, `public/fonts/noto-sans-arabic/OFL.txt`, `public/fonts/vazirmatn/OFL.txt`, `public/fonts/ibm-plex-sans-arabic/OFL.txt`, `public/licenses/` and the header of `public/js/admin.js`.
