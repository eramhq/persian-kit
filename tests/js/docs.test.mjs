import { test } from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import { validate, anchors } from '../../scripts/check-docs.mjs';

function fixture(run) {
    const root = fs.mkdtempSync(path.join(os.tmpdir(), 'pk-docs-test-'));
    const write = (file, value) => { const target = path.join(root, file); fs.mkdirSync(path.dirname(target), { recursive: true }); fs.writeFileSync(target, value); };
    const nav = { schemaVersion: 1, defaultLocale: 'en', locales: ['en', 'fa'], entry: 'overview', sections: [{ id: 'start', title: { en: 'Start', fa: 'شروع' }, pages: ['overview'] }] };
    const page = '---\ntitle: "Start"\ndescription: "Read this."\n---\n# Start\n\n## Details\n';
    const manifest = () => write('docs/navigation.json', JSON.stringify(nav));
    manifest(); write('docs/en/overview.md', page); write('docs/fa/overview.md', page.replace('Start', 'شروع'));
    const check = () => validate(root, { runtime: false });
    try { run({ root, write, nav, page, manifest, check }); }
    finally { fs.rmSync(root, { recursive: true, force: true }); }
}

test('valid bilingual navigation and metadata', () => fixture(({ check }) => assert.deepEqual(check().errors, [])));
for (const [name, edit] of [
    ['schema', n => { n.schemaVersion = 2; }],
    ['unknown schema field', n => { n.extra = true; }],
    ['entry', n => { n.entry = 'absent'; }],
    ['duplicate page', n => { n.sections[0].pages.push('overview'); }],
    ['duplicate section', n => { n.sections.push(n.sections[0]); }],
    ['traversal ID', n => { n.sections[0].pages.push('../outside'); }],
    ['missing English', n => { n.sections[0].pages.push('missing'); }],
    ['missing localized title', n => { delete n.sections[0].title.fa; }],
    ['null section', n => { n.sections.push(null); }],
]) test(`rejects ${name}`, () => fixture(({ nav, manifest, check }) => { edit(nav); manifest(); assert.ok(check().errors.length); }));

test('missing Persian warns and resolves local links to English without translated fragment', () => fixture(({ root, write, check, page }) => {
    fs.unlinkSync(path.join(root, 'docs/fa/overview.md'));
    write('README.md', '[فارسی](docs/fa/overview.md#شروع)');
    assert.equal(check().errors.length, 0); assert.equal(check().warnings.length, 1);
}));
test('an existing invalid Persian page fails, not falls back', () => fixture(({ write, check }) => {
    write('docs/fa/overview.md', '# فقط عنوان\n'); assert.ok(check().errors.some(e => e.includes('metadata')));
}));
test('metadata, duplicate H1 and fenced H1 handling', () => fixture(({ write, check, page }) => {
    write('docs/en/overview.md', page + '\n```text\n# Example only\n```\n'); assert.equal(check().errors.length, 0);
    write('docs/en/overview.md', page + '\n# Second\n'); assert.ok(check().errors.some(e => e.includes('H1')));
    write('docs/en/overview.md', page.replace('description: "Read this."', 'description: ""')); assert.ok(check().errors.some(e => e.includes('metadata')));
}));
test('links, anchors, reference images, encoded paths and nested relative paths', () => fixture(({ write, check, page }) => {
    write('docs/assets/a file.svg', '<svg/>');
    write('docs/en/sub/notes.md', '# Notes\n\n[Page](../overview.md#details)\n![Image][asset]\n\n[asset]: <../../assets/a file.svg> "Title"\n');
    assert.equal(check().errors.length, 0);
    write('README.md', '[Broken](docs/en/overview.md#absent)'); assert.ok(check().errors.some(e => e.includes('anchor')));
    write('README.md', '![Broken](docs/assets/missing.svg)'); assert.ok(check().errors.some(e => e.includes('reference')));
    write('README.md', '[Broken][undefined]'); assert.ok(check().errors.some(e => e.includes('Undefined')));
    write('README.md', '[Outside](../outside.md)'); assert.ok(check().errors.some(e => e.includes('escapes')));
}));
test('GitHub heading basics, Unicode and duplicate suffixes', () => {
    assert.deepEqual([...anchors('# API `some_name`\n## API `some_name`\n## فارسی ساده\n')], ['api-some_name', 'api-some_name-1', 'فارسی-ساده']);
});

test('rejects null navigation with a validation error', () => fixture(({ write, check }) => {
    write('docs/navigation.json', 'null'); assert.ok(check().errors.length);
}));
test('counts setext body H1 and rejects an additional one', () => fixture(({ write, page, check }) => {
    write('docs/en/overview.md', page + '\nAnother title\n=============\n');
    assert.ok(check().errors.some(e => e.includes('H1')));
}));
