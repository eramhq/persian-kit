# Development Guide

## Local Setup

```bash
composer install
npm ci
npm run build
```

The plugin expects built assets in `public/`. If they are missing, the admin UI will show a notice.

## Common Commands

### Run unit tests

```bash
composer test
```

### Run integration tests

```bash
composer test:integration:setup   # once: test database, wordpress-develop checkout, PHPUnit 9.6
composer test:integration
composer test:integration:polylang   # the multilingual tests, with Polylang
```

Integration tests run on WordPress's PHPUnit 9.6 runner with `phpunit-integration.xml.dist`; unit tests use PHPUnit 10 and `phpunit.xml.dist`. The setup script reads the database settings from the site's `wp-config.php` and, on Local, finds the site's MySQL socket. The site's database must be running. Override any setting with `WP_TEST_DB_HOST`, `WP_TEST_DB_USER`, `WP_TEST_DB_PASSWORD`, `WP_CORE_DIR` or `WP_VERSION`.

When WooCommerce is installed next to the plugin (`wp-content/plugins/woocommerce`), or in the directory `PERSIAN_KIT_TESTS_WOOCOMMERCE_DIR` names, the integration tests load it and create its tables, and the WooCommerce checkout and My Account tests run; otherwise those tests are skipped. Contact Form 7 (`contact-form-7`, `PERSIAN_KIT_TESTS_CF7_DIR`) and ACF (`advanced-custom-fields`, `PERSIAN_KIT_TESTS_ACF_DIR`) load the same way for the form tests. CI downloads the releases pinned in `.github/workflows/ci.yml`. Set `PERSIAN_KIT_TESTS_WITHOUT_WOOCOMMERCE=1`, `PERSIAN_KIT_TESTS_WITHOUT_CF7=1` or `PERSIAN_KIT_TESTS_WITHOUT_ACF=1` to run the suite without one.

Polylang filters every query by language once it has languages, so it loads only in its own run: `composer test:integration:polylang` sets `PERSIAN_KIT_TESTS_MULTILINGUAL=1` and runs the `polylang` group, which the main run leaves out. Polylang loads from `wp-content/plugins/polylang` or `PERSIAN_KIT_TESTS_POLYLANG_DIR`; without it those tests are skipped. WPML is commercial and has no automated integration tests.

### Run JavaScript tests

```bash
npm ci
npm run test:js
```

The tests in `tests/js/` run on Node's test runner. The WooCommerce scripts and the classic editor's date fields are tested in a [jsdom](https://github.com/jsdom/jsdom) page, those that use jQuery with the release WordPress ships; both are dev dependencies only. The date field tests load the built `public/js/datepicker.js`, so run `npm run build` first.

`tests/fixtures/persian-years.json` lists 1 Farvardin and the length of Esfand for 1300–1500 AP as the date picker computes them. `tests/js/calendar-agreement.test.mjs` checks it against the picker, the picker bundle's `PersianKitCalendar` and `resources/js/jalali.js`, and `tests/Unit/DateConversion/CalendarAgreementTest.php` against daynum, so a picked date can't be saved a day off.

### Static analysis and coding standards

```bash
composer phpstan   # PHPStan level 6 with WordPress and WooCommerce stubs
composer phpcs     # WordPress security, SQL and i18n sniffs
```

Code style follows PSR-12; PHPCS only enforces the WordPress security, database and i18n rules.

### Build frontend assets

```bash
npm run build
```

### Create a distribution zip

```bash
npm run dist
```

## Continuous Integration

`.github/workflows/ci.yml` runs on every push to `main` and on pull requests:

- unit tests, PHPStan and PHPCS on PHP 8.1 and 8.4
- integration tests against WordPress 7.1, with WooCommerce, Contact Form 7 and ACF, on PHP 8.1 and 8.4
- JavaScript tests on Node 22
- `npm run dist` and Plugin Check on the built plugin

## Release Flow

1. Update the version in `persian-kit.php` (`Version`), `src/constants.php` (`PERSIAN_KIT_VERSION`), `readme.txt` (`Stable tag`) and `package.json`.
2. Add the release to `CHANGELOG.md` and the `readme.txt` changelog.
3. Run `composer test`, `composer test:integration`, `composer test:integration:polylang`, `composer phpstan` and `composer phpcs`.
4. Run `npm run dist`.
5. Run Plugin Check on `dist/persian-kit/`, then install the zip in a clean WordPress site and verify activation.

## Notes

- Source assets live in `resources/`.
- Built assets live in `public/`.
- `eram/abzar` and `eram/daynum` are copied into `packages/` under the `PersianKit\Dependencies\` namespace by wp-scoper after every `composer install`/`update`. The plugin loads them from there; `vendor/` is development-only.
- `scripts/prepare-bundled-libraries.php` then copies each library's LICENSE next to it and adds a `phpcs:ignoreFile` line to each copied library file, so Plugin Check reviews only Persian Kit's own code. Their exception messages are never printed, and as plain PHP libraries they can't use WordPress escaping.
- `public/js/datepicker.js` bundles [intl-datepicker](https://github.com/eramhq/intl-datepicker) with only the Persian calendar and Persian labels (`resources/entries/datepicker-entry.js`): 138 kB, 34 kB gzipped. The package is pre-1.0, so `package.json` pins its exact version; read its changelog before updating. `@internationalized/date`, its calendar library, is pinned too, because the entry imports it for `PersianKitCalendar`. `resources/js/date-field.js` connects the picker to form fields; `woocommerce-date-fields.js` (WooCommerce's admin date fields) and `classic-date-fields.js` (the post date in the classic editor and Quick Edit) mark their fields for it. `resources/js/jalali.js` is used only by the block editor's date row (`gutenberg-jalali-date.js`).
- Every JavaScript file in `public/js/` is built from `resources/`. `npm run build` empties `public/` first, so a new script, or a data file such as `resources/data/ir-cities.json`, must be added to `scripts/copy-assets.mjs` and `scripts/verify-build.mjs`. `resources/` is not shipped.
- `npm run build:pot` includes JavaScript strings. Scripts with translatable strings call `wp_set_script_translations()`.
- The dist script respects `.distignore`.
