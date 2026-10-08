# Documentation maintenance and website import contract

This file is for maintainers and is **outside public navigation**. Start at [English](en/overview.md) or [فارسی](fa/overview.md). [navigation.json](navigation.json) is the authoritative page order. The structure follows Abzar PHP's bilingual documentation contract, adapted to WordPress site owners.

## Authoring and translations

- Public pages belong in `docs/en/` and `docs/fa/`, with matching lowercase English paths. Keep `README.md` and `README.fa.md` at the repository root short. Keep WordPress `readme.txt` in its required format and consistent with the guides.
- Each listed page has `title` and `description` as non-empty, double-quoted, single-line YAML strings (JSON string escaping), followed by exactly one body H1. The site must suppress a duplicate title; keep the H1 for GitHub.
- Use plain Markdown and relative links. Shared images belong in `docs/assets/` (`.gitkeep` reserves it; no documentation screenshots are currently claimed). Do not use raw HTML, scripts, MDX or custom components. Fenced HTML/PHP examples are literal source, not rendering dependencies.
- One main home per topic: settings/defaults in `settings`, input/display/storage distinctions in the owner guides, helper contracts in `developers/api`, extension hooks in `developers/hooks`, contextual examples in `developers/recipes`.
- `REFERENCE.md` and `UTILITIES.md` retain historical headings as compatibility indexes. `DEVELOPMENT.md` remains an internal engineering guide. Locale README indexes are GitHub entry links. None is in public navigation.
- Write complete Persian translations, using familiar words and English technical terms when clearer. Use correct half-spaces, no authored vowel marks or visible ezafe. Keep code, identifiers and exact outputs unchanged. Interface instructions must match `languages/persian-kit-fa_IR.po`; the settings guide includes both UI languages to remove ambiguity.
- Update both languages together. Never use English prose as a Persian placeholder. A missing Persian page is reported, not fabricated. Human review is still needed for freshness and meaning; matching paths alone do not prove translation quality.

## Validation

Run from the repository root with Composer dependencies/scoped packages installed, PHP and Node available (CI uses Node 22):

```bash
npm run docs:check
npm run docs:test
npm run docs:examples
```

Composer aliases `composer docs:check` and `composer docs:examples` are also available and require Node. The CI documentation job runs these without building the plugin or connecting to WordPress. The repository's existing runtime/unit/integration jobs remain responsible for plugin behavior.

The dependency-free Node checker validates the exact navigation schema, IDs, section titles, entry membership and English availability; existing public translations need metadata and one body H1. It scans all repository-root Markdown and all docs Markdown for local file/directory links, inline/reference-style images and links, and Markdown heading fragments (including duplicate headings). It ignores fenced code and inline code. It does not fetch remote URLs. Missing listed Persian pages resolve to English for link checking and are warnings; fallback fragments are omitted. Other missing files, broken anchors or invalid existing translations fail. The authoring subset is deliberately small: double-quoted metadata, ordinary Markdown destinations, no custom HTML anchors.

`docs:test` exercises failure cases: invalid schema/IDs/entry, duplicates, missing English, missing Persian fallback, broken anchors, images, reference links and invalid metadata/H1. `docs:examples` syntax-checks PHP fences with PHP and shell fences with `bash -n`. Only PHP fences immediately followed by an exact `text` output fence execute, in fresh subprocesses with [the isolated helper bootstrap](../scripts/docs-example-bootstrap.php). That bootstrap uses UTC, bundled functions and pass-through filters; it never boots WordPress or opens a database. Contextual theme/form snippets are syntax-only. No maintenance command, import, meta write, browser interaction or live database operation is executed by documentation checks.

[contracts.json](contracts.json) is a reviewed snapshot of module defaults/dependencies, public helper signatures, hook names/argument counts declared runtime requirements (including locked library requirements), and CI integration version coverage. [docs-runtime.php](../scripts/docs-runtime.php) reads static module methods, reflection and PHP tokens without booting WordPress. A source change fails until maintainers review its effects and update both languages and the snapshot. The checker also compares settings table rows/labels against implementation and the Persian PO, covers every public helper/hook, and checks documented minimum versions/release identifiers. [settings-labels.json](settings-labels.json) maps option keys to exact UI strings and is not public navigation. These mechanical checks cannot prove every prose claim: inspect the implementation and relevant tests as part of review, rather than blindly refreshing snapshots.

For an intentional implementation change, inspect the proposed contract without overwriting the reviewed file:

```bash
php scripts/docs-runtime.php > /tmp/persian-kit-docs-contracts.json
diff -u docs/contracts.json /tmp/persian-kit-docs-contracts.json
```

Useful evidence lives in [module classes](../src/Modules), [settings/lifecycle](../src/Core), [installation](../src/Service/Installation), [import services](../src/Service/Import), [unit tests](../tests/Unit), [integration tests](../tests/Integration) and [browser-script tests](../tests/js). Requirements come from the plugin header, Composer dependencies and CI's actual version matrix, not upstream marketing. Integration tests have a dedicated test database and can destroy its contents: follow [DEVELOPMENT.md](DEVELOPMENT.md), verify the test database name/host first and never point them at the site's real database.

## Release source policy

Public website documentation comes only from a **published GitHub release**, including labeled prereleases, and the **exact commit resolved from its tag** (peel annotated tags). Draft releases, unpublished tags, uncommitted files and branch HEADs are not public sources. A source version of `1.0.0` is not evidence that a release was published. No release availability claim is made by these checks.

Before a future release, include both locale trees, `docs/navigation.json`, compatibility files and all referenced assets in the commit being tagged. Run documentation checks on that snapshot. `.distignore` excludes `docs/` from the installable plugin ZIP; this is intentional and does not remove files from the Git source snapshot. The importer must fetch the tag's source commit rather than the plugin ZIP. No cross-repository synchronization or publishing automation is added here.

## Importer contract

1. Read schema version 1: `defaultLocale` is `en`, `locales` is `["en", "fa"]`. IDs are unique locale-relative paths without `.md`. Entry is listed. Sections supply translated section titles; frontmatter supplies page titles. Both languages share order and grouping.
2. Publish only listed pages. Do not expose maintenance notes, contracts, development plans or compatibility indexes just because they exist in the repository.
3. Require English. For a missing Persian translation, use the English content and show exactly: «این صفحه هنوز به فارسی ترجمه نشده است. متن انگلیسی را می‌خوانید.» Render that fallback content LTR with Persian navigation. Preserve the page on language switching; do not invent translated anchors.
4. Resolve relative links from each original source path, rewriting links between public pages into website routes. Documentation README links map to the entry page. For a missing Persian target, route to English fallback; drop a fragment that belongs only to the absent translation.
5. Resolve all referenced assets from the same release commit. Keep source-code, directory and non-public file links on GitHub at that commit, preserving applicable fragments. Never turn a compatibility index or source file into an invented website route.
6. Keep the body H1 for repository readers and avoid duplicate title rendering on the site. No repository page depends on raw HTML being preserved.

The local Eram publishing contract and Abzar navigation/maintenance reference were read while preparing this structure. The website was not changed or built here. Its Markdown rendering, routes, asset mapping and fallback behavior remain importer responsibilities to verify in a separate website preview. Committing, tagging and publishing a future release are separate authorized actions; documentation checks do none of them.
