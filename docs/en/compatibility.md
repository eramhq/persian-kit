---
title: "Compatibility, updates and removal"
description: "Know tested versions, integration limits and what removal preserves or deletes."
---
# Compatibility, updates and removal

## Release and test evidence

This tree declares **1.0.1** in the plugin header, constants, package version and WordPress Stable tag; 1.0.0 was the first stable version. No separate formal API/backward-compatibility guarantee is present. Treat documented helpers/hooks as integration points, review the changelog on upgrades and avoid depending on undocumented internals. Bundled Abzar/Daynum dependencies still use beta-compatible constraints; “stable plugin” does not turn every bundled internal class into a stable public API.

The [CI configuration](../../.github/workflows/ci.yml) defines these integration rows (configuration is evidence of intended coverage, not proof of a particular run passing):

| PHP | WordPress | WooCommerce | Yoast | Gravity Forms | WPForms Lite |
| --- | --- | --- | --- | --- | --- |
| 8.4 | 7.1.2 | 11.1.2 | 28.6 | 3.1.3 | 2.0.2.1 |
| 8.1 | 6.8.10 | 9.9.7 | 28.2 | 2.9.31 | 1.9.1.1 |

Other pins: CF7 6.1.7, ACF 6.8.10, Forminator 1.57.3, Rank Math 1.0.279, Polylang 3.8.10 and Parsi Date 6.4. Source-plugin tests use unpinned latest Persian WooCommerce/shipping downloads. WPML has unit-level coverage but no automated integration run. Multisite activation/import tests cover specific cases, not every network setup. WooCommerce declares HPOS compatibility; tests cover classic/block checkout and legacy/HPOS order paths, with newer features skipped on older versions.

## Languages and SEO

WPML and Polylang modules default on. With languages configured, display follows the page/email language; admin dates follow **Users > Profile > Language**. Persian, Pashto and Sorani Kurdish read Jalali by default; writing normalization, Persian slugs and half-space tools target Persian content. A locale assigned explicit month names can also receive Jalali display. On single-language sites, date display is not restricted to a Persian locale, including admin dates. Admin font and WooCommerce email-digit/font checks have their own locale rules. See [hooks](developers/hooks.md) for locale extensions.

Yoast SEO and Rank Math integrations preserve Gregorian machine dates and convert supported toman schema prices to rials; date archive titles can name Jalali months. Visible browser titles can use Persian digits while machine metadata keeps original digits. This does not guarantee every SEO extension's output. Avoid two plugins converting the same dates; the compatibility cards explain overlaps but do not disable other plugins.

Weglot is not integrated because it translates rendered pages without exposing a distinct WordPress locale in the same way. Other locale-switching plugins require deliberate configuration and testing; a filter opt-in is not tested blanket support.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Missing settings/picker assets | Build or install a complete ZIP; inspect browser errors and stale/minified assets. |
| Dates remain Gregorian | Module, locale, date format and whether the theme calls supported WordPress functions. Test advanced conversion on staging only. |
| Wrong local day/hour | WordPress timezone, GMT versus local input, and duplicate converters; do not add offsets to Unix timestamps. |
| Tools fail or stop | REST authentication/nonce, `manage_options`, security rules, request limits; review logs and explicitly resume, or use CLI. |
| Forms stop validating | Integration availability/switch and whether the field/tag/class is supported. |
| Old URLs fail | Permalink structure, disabled date archives, plugin activation and source migration report. |
| Changes do not appear | Clear the relevant cache and compare an uncached request. There is no universal cache integration. |

## Update, deactivate, uninstall

Back up before updates, read [CHANGELOG](../../CHANGELOG.md), test supported workflows on staging and install the intended built version. Existing settings are retained/migrated; new keys use defaults. Older schema migrations preserve earlier digit/save-normalization behavior, so a long-lived site can differ from fresh-install defaults.

Deactivation stops hooks but keeps settings and stored content changes. Replace custom [form fields](forms.md) first. If using `IRHT`/`IRHR`, plan a switch to supported currency and convert amounts before deactivation: with Persian Kit removed, symbols disappear and saving WooCommerce's currency settings can reset an unlisted currency. Old Jalali URL compatibility needs the active plugin.

Deleting the plugin through WordPress runs [uninstall.php](../../uninstall.php): it removes Persian Kit settings, schema/welcome/integration state, normalization/import progress, field-usage transients and the import log table, across every site in multisite. It does **not** restore normalized content, imported addresses/statuses, saved slugs or custom field values. Download reports and complete needed undo **before deletion**.

Website docs become public only from a published release, including labeled prereleases, at the exact commit resolved from its tag. The release source snapshot includes the navigation and both locale trees; the installable ZIP excludes repository documentation. See the [maintenance/import contract](../README.md).
