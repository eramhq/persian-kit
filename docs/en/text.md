---
title: "Text, digits and typography"
description: "Choose display-only digits, saved-text normalization and editor tools."
---
# Text, digits and typography

## Display Persian digits

Use **Persian Kit > Display > Persian digits**, then **Save changes**. The module is off on a fresh install; its dates, counts and prices options are on but inactive until the module is enabled. WooCommerce emails have a separate off-by-default option. [Settings](settings.md) lists exact labels.

With it enabled, `Order 123` in a supported post title/content becomes `Order ۱۲۳` on the frontend. It does not rewrite the saved post. Supported surfaces include excerpts, comments, many widget and navigation labels, term names, browser titles and WooCommerce short descriptions. Tags, attributes, URLs, HTML character references and `pre`, `code`, `kbd`, `samp` contents are preserved. The site name in the browser title retains its typed digits.

Admin screens, REST responses, feeds and machine-oriented head output retain their digits. This is not a whole-page replacement: text hardcoded in templates, WooCommerce cart product names and arbitrary plugin output need separate testing. General mail rendered before `wp_mail()` can already contain converted text; WooCommerce emails follow [their own rules](woocommerce.md).

## Search and saved letters

**Writing > Persian ی and ک** is on by default. Search matches variants of ي/ی, ك/ک and English/Persian digits in the main search query's title, excerpt and content (or its selected search columns), without changing stored content. For example, searching `كتاب 1405` can find `کتاب ۱۴۰۵`. Media queries that search attachment filenames keep WordPress's own query; third-party search indexes are not covered.

**Fix letters on save** is off by default. When enabled, saving `كتاب يكي ١٢` produces `کتاب یکی ۱۲`: Arabic Yeh/Kaf and Arabic-Indic digits change. ASCII digits stay ASCII. The scope is public post types and menu items (title, excerpt, content), new comments (author and content), and term names/descriptions. Auto-drafts, revisions and autosaves are skipped. HTML/code portions of content are protected; this is not a rewrite of all metadata.

**Also replace ة with ه** is optional. **Add half-spaces on save** is independently optional and affects posts/menu items, not comments or terms. For example, `می خواهم کتاب ها را` becomes `می‌خواهم کتاب‌ها را`. This is a pattern-based correction and can join words incorrectly. Disabling the option stops future corrections; it does not restore the original text. Use backups or available WordPress revisions where appropriate. [Batch normalization](maintenance.md) has a different scope and no revision recovery.

## Editor and admin font

**Writing > Half-space key** is on by default. In Classic Editor (visual/text) and the post block editor, press **Shift+Space** between `می` and `خواهم` to insert a ZWNJ: `می‌خواهم`. Classic Editor also has a toolbar button. On multilingual sites it follows the content language; reload the editor after changing that language. It changes what you type and save, not every existing space.

**Display > Admin font** is on with **Vazirmatn**. **Font** also offers **Noto Sans Arabic** and **IBM Plex Sans Arabic**. The font loads for Persian or RTL admin languages, not the public theme. WooCommerce's email font is separate. Turn off the module to stop loading its font.

Related: [dates](dates.md), [slugs](links.md), [multilingual behavior](compatibility.md), [developer helpers](developers/api.md).
