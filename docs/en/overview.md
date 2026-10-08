---
title: "Persian Kit"
description: "Start using Persian dates, text tools and integrations in WordPress."
---
# Persian Kit

Persian Kit adds Jalali dates, Persian writing tools and optional integrations to WordPress. Each module has its own switch. This source tree identifies itself as **1.0.1**, a patch release following 1.0.0; a version in source is not proof that a GitHub release has been published. See [compatibility and release status](compatibility.md).

## First use

1. [Install and activate](installation.md) the built plugin. Open **Persian Kit** in the WordPress admin menu.
2. In **Display**, leave **Jalali dates** on. Check WordPress **Settings > General > Timezone** and your date format. A post dated Gregorian `2026-10-02`, displayed with `Y/m/d`, reads `1405/07/10`; its stored Gregorian date remains unchanged.
3. To show `۱۲۳` instead of `123` in supported content, turn on **Persian digits**, then **Save changes**. It starts off. View a post containing `شماره 123`: its visible text becomes `شماره ۱۲۳`, while the saved text stays the same.
4. In **Writing**, leave **Fix letters on save** off until you want saved text changed. Search already matches Arabic and Persian forms of ی and ک while **Persian ی and ک** is on.
5. Test a post, its archive, and any form or checkout you use. Integrations start enabled but only run when the required plugin is active and supported.

## Choose a guide

- [Settings](settings.md): every module, exact labels and new-install defaults.
- [Text, digits and typography](text.md): display changes, saved text and the half-space key.
- [Dates](dates.md) and [links and archives](links.md): calendars, editor dates and URLs.
- [WooCommerce](woocommerce.md) and [forms](forms.md): checkout, prices, emails and date fields.
- [Normalize existing posts](maintenance.md) and [switch plugins](switching.md): operations that change stored data.
- [Developer API](developers/api.md), [hooks](developers/hooks.md) and [recipes](developers/recipes.md).

Ordinary display switches do not rewrite the database. Saving normalized text, saving a new slug, form/checkout input normalization and migration tools can change stored values. Turning a module off does not reverse those writes.
