---
title: "Normalize existing posts"
description: "Preview and run character normalization with a database backup."
---
# Normalize existing posts

## What this tool changes

**Persian Kit > Tools > Fix letters in existing posts** fixes Arabic ي/ك and Arabic-Indic digits in existing titles, excerpts and content. With saved **Also replace ة with ه** on it includes that replacement. It does **not** apply the half-space fixer, despite that option appearing in the same writing module. It is separate from “Fix letters on save” and can run with that option off.

The admin tool lists public post types; attachments are initially unchecked. It skips auto-drafts and non-Persian posts on multilingual sites. It does not normalize comments, terms, arbitrary metadata, slugs or all database tables. HTML/code portions of content are protected. Malformed content that the segmenter cannot process is skipped.

## Preview and run

1. Back up the database and verify that you can restore it. Prefer a staging copy first.
2. Save the writing settings. Keep **Persian ی and ک** enabled for the admin REST tool, and select the post types under **Tools**.
3. Choose **Count posts**. This preview scans for actual changes without saving them; the initial status estimate can count characters in protected content too.
4. Choose **Fix posts…**, read and accept its confirmation, and monitor progress. Opening the page does not automatically resume a job. **Resume fixing posts…** resumes; **Start over** resets progress, not previous edits.

Example on an isolated fixture: a post title `كتاب ١٢` becomes `کتاب ۱۲`, while its slug and dates stay unchanged. The exact number of affected posts depends on your database and saved settings; no universal count is promised.

**Recovery:** writes go directly to the posts table, clear the post cache, do not fire normal save hooks and do not create revisions. There is no built-in undo for this tool. Restore the database backup to recover original values. Disabling/uninstalling the plugin does not undo these writes. Other plugins' search indexes or full-page caches may need their own refresh because save hooks do not run.

## WP-CLI

Read-only preview (with respect to content):

```bash
wp persian-kit normalize --dry-run --post-type=post,page --batch-size=100
```

After a backup, the following **writes to the database**:

```bash
wp persian-kit normalize --post-type=post,page --batch-size=100
```

CLI defaults to `post,page` and batch size 100, clamped to 1–500. The CLI accepts its provided post-type list rather than applying the admin's public-type selector. It shares progress with the UI and is available even when the normalization module is off. `--restart` clears progress before running, even when combined with `--dry-run`; omit it for a preview that preserves the saved cursor.

The admin REST routes require `manage_options` and WordPress REST authentication (the UI supplies a nonce). CLI access uses your shell/WordPress environment and has no equivalent capability check in this command. Protect that access. Never run these examples on a live database merely to test documentation.

Related: [text on save](text.md), [switching with an undo log](switching.md), [developer hooks](developers/hooks.md).
