---
title: "Installation"
description: "Install the built plugin or prepare a development checkout."
---
# Installation

## Requirements

- WordPress **6.8+** and PHP **8.1+**, as declared in [the plugin header](../../persian-kit.php) and [Composer](../../composer.json).
- WooCommerce **9.9+** only for its integration. Forminator **1.50+**, Gravity Forms **2.9+** and WPForms **1.9.1+** for theirs. Other integrations check plugin availability without declaring a version minimum; this is not a promise about every historical version.
- The bundled Abzar library requires PHP `mbstring`. PHP `intl` is needed for correct Persian sorting; without it the sorting helper falls back to byte order. The plugin does not enforce these extension requirements with an activation check.
- JavaScript and built assets are needed for date pickers and the interactive settings/tools screens. PHP display helpers and server-side validation are separate.

See [the tested combinations](compatibility.md), rather than treating minimum versions as a universal compatibility guarantee.

## Install a built ZIP

The repository provides a ZIP builder and CI uploads its result as `persian-kit`. Download `persian-kit.zip` from the [1.0.1 release](https://github.com/eramhq/persian-kit/releases/tag/v1.0.1), or use a built ZIP for your intended version. A GitHub source archive is not the same as that built ZIP. The release asset is the installable build; use the tagged source snapshot for repository documentation.

1. Back up the site before replacing an existing installation.
2. In WordPress, open **Plugins > Add New Plugin > Upload Plugin**, select the built ZIP, install it and activate **Persian Kit**.
3. Open **Persian Kit**, review the welcome notice and [first-use steps](overview.md#first-use).

Expected result: the settings menu appears, Jalali dates are enabled, and Persian digit conversion is off on a fresh site. An upgrade keeps existing settings; new keys use their registered defaults. Network activation initializes each site and newly created sites, but settings are per site.

## Build from source

Run from the repository root in a development checkout:

```bash
composer install
npm ci
npm run build
```

Composer prepares namespaced dependencies in `packages/`; the asset build writes `public/`. Activate that checkout in a local WordPress installation. To build the installable ZIP:

```bash
npm run dist
```

The distribution script requires `wp` (WP-CLI), `composer`, `npm`, `rsync` and `zip`. It installs dependencies, rebuilds assets, generates the translation template and replaces `dist/`, producing `dist/persian-kit.zip`. It does not publish a release. CI uses Node 22; Node and Composer are build tools, not visitor-side requirements.

Repository documentation is intentionally excluded from the installable ZIP by [.distignore](../../.distignore). It remains in the release's Git commit for the website importer. [Development notes](../DEVELOPMENT.md) cover tests and builds.

## Common installation problems

A source checkout missing `packages/` or built `public/` files is incomplete. Build it before testing activation. If an integration is unavailable, check its active state and minimum version on **Integrations**. WooCommerce has its own tab while active. For updates or removal, read [compatibility](compatibility.md).
