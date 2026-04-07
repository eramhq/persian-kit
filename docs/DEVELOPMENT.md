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
composer test:integration
```

### Build frontend assets

```bash
npm run build
```

### Create a distribution zip

```bash
npm run dist
```

## Release Flow

1. Update version references.
2. Run `composer test`.
3. Run `composer test:integration`.
4. Run `npm run build`.
5. Run `npm run dist`.
6. Install the generated zip in a clean WordPress site and verify activation.

## Notes

- Source assets live in `resources/`.
- Built assets live in `public/`.
- PHP dependencies are loaded from `packages/` in production and `vendor/` in development.
- The dist script respects `.distignore`.
