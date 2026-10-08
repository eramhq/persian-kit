---
title: "Slugs, archives and links"
description: "Use Persian slugs and Jalali archive or post URLs without rewriting saved dates."
---
# Slugs, archives and links

## Persian slugs

**Persian Kit > Writing > Persian slugs > Use Persian slugs** is on by default, as is its module. For a new post, `نمونه نوشته ۱۴۰۵` becomes `نمونه-نوشته-1405`; `می‌خواهم` becomes `می-خواهم`. Letters are normalized and digits become English. Latin-only slugs and WooCommerce attribute names such as `pa_color` remain under WordPress's rules.

Existing slugs are not rewritten. A new/saved slug is stored data; turning this option off does not restore an earlier slug. Old ZWNJ slugs and WordPress's percent-encoded Persian slugs are looked up for compatibility; ZWNJ/hyphen equivalents can redirect to the canonical post. Links are percent-encoded while the module is on, even with **Use Persian slugs** off, so already-saved Persian slugs remain usable.

## Jalali archives

**Display > Jalali dates > Jalali archives and calendar** is on. Standard Archives and Calendar widgets/blocks use Jalali months and link to Jalali date archives. `/1405/07/` selects Mehr 1405, `/1405/` a year and `/1405/07/10/` a day. These are query ranges over Gregorian stored dates. Gregorian archive URLs still work. Turning the archive/calendar option off makes those lists Gregorian; it does not disable Jalali archive URL recognition while the date module is active.

Use WordPress's Archives or Calendar block/widget; Persian Kit does not register a separate shortcode or standalone block for this feature. Confirm your permalink setup and that another plugin has not disabled date archives. The Jalali archive list supports `monthly`, `yearly` and `daily`; `weekly` and `postbypost` retain WordPress’s output. Options such as ordering, limits, counts, post type and HTML/dropdown format are applied to the supported periods.

## Dates in post links

**Jalali dates in post links** is off. It matters only for post permalink structures containing date tokens such as `/%year%/%monthnum%/%day%/%postname%/`. With the option on, a post dated `2026-10-02` gets date components `/1405/07/10/`; it does not add dates to a structure that lacks them. Old Gregorian links redirect to the current canonical link; turning the option off redirects Jalali post links back to Gregorian.

The plugin also registers legacy Jalali URL handling while the date module is off. This requires Persian Kit to remain active; deactivating the whole plugin removes that handling. Test bookmarked URLs on staging before changing modules or replacing another date plugin. Persian-digit URL components are not a supported import format.

On multilingual sites only Persian posts receive Jalali post permalinks. See [compatibility](compatibility.md), [switching plugins](switching.md) and the [extension filters](developers/hooks.md).
