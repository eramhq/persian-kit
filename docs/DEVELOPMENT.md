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
```

Integration tests run on WordPress's PHPUnit 9.6 runner with `phpunit-integration.xml.dist`; unit tests use PHPUnit 10 and `phpunit.xml.dist`. The setup script reads the database settings from the site's `wp-config.php` and, on Local, finds the site's MySQL socket. The site's database must be running. Override any setting with `WP_TEST_DB_HOST`, `WP_TEST_DB_USER`, `WP_TEST_DB_PASSWORD`, `WP_CORE_DIR` or `WP_VERSION`.

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
- integration tests against WordPress 7.1 on PHP 8.1 and 8.4
- `npm run dist` and Plugin Check on the built plugin

## Release Flow

1. Update the version in `persian-kit.php` (`Version`), `src/constants.php` (`PERSIAN_KIT_VERSION`), `readme.txt` (`Stable tag`) and `package.json`.
2. Add the release to `CHANGELOG.md` and the `readme.txt` changelog.
3. Run `composer test`, `composer test:integration`, `composer phpstan` and `composer phpcs`.
4. Run `npm run dist`.
5. Run Plugin Check on `dist/persian-kit/`, then install the zip in a clean WordPress site and verify activation.

## Notes

- Source assets live in `resources/`.
- Built assets live in `public/`.
- `eram/abzar` and `eram/daynum` are copied into `packages/` under the `PersianKit\Dependencies\` namespace by wp-scoper after every `composer install`/`update`. The plugin loads them from there; `vendor/` is development-only.
- `scripts/prepare-bundled-libraries.php` then copies each library's LICENSE next to it and adds a `phpcs:ignoreFile` line to each copied library file, so Plugin Check reviews only Persian Kit's own code. Their exception messages are never printed, and as plain PHP libraries they can't use WordPress escaping.
- Every JavaScript file in `public/js/` is built from `resources/`. `npm run build` empties `public/` first, so a new script must be added to `scripts/copy-assets.mjs` and `scripts/verify-build.mjs`.
- `npm run build:pot` includes JavaScript strings. Scripts with translatable strings call `wp_set_script_translations()`.
- The dist script respects `.distignore`.
