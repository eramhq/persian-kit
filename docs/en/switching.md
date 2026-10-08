---
title: "Switch from another plugin"
description: "Review, import and undo supported settings and data migrations."
---
# Switch from another plugin

## Review before changing data

Open **Persian Kit > Tools > Switch from another plugin** with `manage_options` access. Supported sources are **Parsi Date**, **Persian WooCommerce** and **Persian WooCommerce Shipping**. Detection can use leftover data after a source plugin was deleted; it is not an importer for every Persian plugin.

Back up the database and theme files. Review the settings mappings, data counts, warnings and selected tasks. A backup acknowledgement is not a backup created by Persian Kit. Use WordPress's own deactivation link for the source plugin: Persian Kit never deactivates it. An active source normally blocks import. Persian WooCommerce is the exception: it may remain active for gateways once all overlapping options are off. Selected setting rows are merged: booleans only turn on, a scalar changes only while Persian Kit still has its default, and province lists merge rather than overwrite an existing restriction.

| Source | Supported work |
| --- | --- |
| Parsi Date | Settings; Archives/Calendar widgets and blocks; ACF field definitions/date values; scanning active theme code for old helper calls |
| Persian WooCommerce | Mapped settings and old province codes in customers/orders |
| Persian WooCommerce Shipping | Mapped settings; province/city IDs in customer/order addresses, zones and store address; selected order-status mappings |

Example: a shipping-plugin order in a `wc-pws-*` status can be mapped to Processing (default) or a selected WooCommerce status. It receives an order note without sending a status email or changing stock. This is a stored order change, not display formatting. Districts can fill an empty address line 2 when the review option allows it.

## What needs manual work

There is no replacement for payment gateways, shipping methods, per-city rates, maps or SMS. Keep required gateway plugins with overlapping options off, or arrange replacements before switching. If keeping Persian WooCommerce active for gateways, first turn off every option that overlaps Persian Kit; its readiness check then permits import.

Parsi Date theme calls need the suggested compatibility code added by a developer; scanning does not rewrite theme PHP. City-based zones need review because ordinary zones do not target individual cities. WP Jalali, Persian-digit date URLs, multiple-address plugin storage and all other sites' customer addresses in a network are not covered. On multisite, users are shared: changing this site's customer address can affect the same user on other sites.

## Run, report and undo

Import runs in batches and can resume after a reload or another visit; download the report when finished. It pauses if the source no longer passes its readiness check, for example when Parsi Date is activated again. The job/lock are shared with WP-CLI. The log records changed fields and attention items in `{prefix}persian_kit_import_log`. Keep the report and resolve items that were skipped; “finished” does not mean every external feature was replaced.

Undo restores a field only if its current value still matches what the import wrote; later edits are kept. It is not a full database rollback. Cancelling/resetting a job does not undo completed writes. Deleting undo data removes the report/log for that source; uninstalling Persian Kit drops the log and recovery data on every site. Backups remain the recovery option for changes undo cannot restore.

## CLI review

These commands review without importing content:

```bash
wp persian-kit import list
wp persian-kit import wp-parsidate --dry-run
```

After review, backup and source deactivation, this command **changes data** and asks for confirmation:

```bash
wp persian-kit import wp-parsidate
```

Sources: `wp-parsidate`, `persian-woocommerce`, `persian-woocommerce-shipping`. Options include `--tasks`, `--skip-settings`, `--status-map`, `--no-district-line`, `--fix-double-dates`, `--yes`, `--undo`, `--report`. Use `wp help persian-kit import` for syntax. `--yes` bypasses prompts; it does not make a backup. Theme scan and report contents are site-specific and are not deterministic example output.

Related: [normalization without undo](maintenance.md), [links](links.md), [compatibility](compatibility.md), [source extension hooks](developers/hooks.md).
