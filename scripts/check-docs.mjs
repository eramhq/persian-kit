import fs from 'node:fs';
import path from 'node:path';
import os from 'node:os';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const repository = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const slugPattern = /^[a-z0-9]+(?:-[a-z0-9]+)*(?:\/[a-z0-9]+(?:-[a-z0-9]+)*)*$/;
const read = file => fs.readFileSync(file, 'utf8');
const same = (a, b) => JSON.stringify(a) === JSON.stringify(b);
const walk = dir => fs.existsSync(dir) ? fs.readdirSync(dir, { withFileTypes: true }).flatMap(item => item.isDirectory() ? walk(path.join(dir, item.name)) : [path.join(dir, item.name)]) : [];
export function unfence(text) {
    return text.replace(/^(`{3,}|~{3,})[^\n]*\n[\s\S]*?^\1\s*$/gm, '');
}
export function anchors(text) {
    const used = new Set();
    for (const match of unfence(text).matchAll(/^ {0,3}#{1,6}\s+(.+?)\s*#*\s*$|^([^\n]+)\n(?:===+|---+)\s*$/gm)) {
        const label = (match[1] ?? match[2]).replace(/!?\[([^\]]+)\]\([^)]*\)/g, '$1').replace(/<[^>]*>/g, '').replace(/&amp;/g, '&');
        const base = label.toLowerCase().replace(/[^\p{L}\p{M}\p{N}_\-\s\u200c]/gu, '').replace(/ /g, '-');
        let id = base, suffix = 0;
        while (used.has(id)) id = `${base}-${++suffix}`;
        used.add(id);
    }
    return used;
}
export function validate(root = repository, { runtime = true } = {}) {
    const errors = [], warnings = [];
    const fail = message => errors.push(message);
    const docs = path.join(root, 'docs');
    let nav;
    try { nav = JSON.parse(read(path.join(docs, 'navigation.json'))); }
    catch (e) { return { errors: [`navigation: ${e.message}`], warnings }; }
    if (!nav || typeof nav !== 'object' || Array.isArray(nav)) return { errors: ['Invalid navigation object'], warnings };
    const keys = (obj, expected) => obj && typeof obj === 'object' && !Array.isArray(obj) && same(Object.keys(obj).sort(), expected.sort());
    if (!keys(nav, ['schemaVersion', 'defaultLocale', 'locales', 'entry', 'sections']) || nav.schemaVersion !== 1 || nav.defaultLocale !== 'en' || !same(nav.locales, ['en', 'fa'])) fail('Invalid navigation schema');
    if (!Array.isArray(nav.sections) || !nav.sections.length) fail('Navigation needs sections');
    const ids = new Set(), sections = new Set();
    for (const section of Array.isArray(nav.sections) ? nav.sections : []) {
        if (!section || typeof section !== 'object') { fail('Invalid section'); continue; }
        if (!keys(section, ['id', 'title', 'pages']) || typeof section.id !== 'string' || !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(section.id) || sections.has(section.id)) fail('Invalid/duplicate section ID');
        sections.add(section.id);
        if (!keys(section.title, ['en', 'fa']) || ['en', 'fa'].some(l => typeof section.title[l] !== 'string' || !section.title[l].trim())) fail(`Invalid section titles: ${section.id}`);
        if (!Array.isArray(section.pages) || !section.pages.length) fail(`Empty/invalid pages: ${section.id}`);
        for (const id of Array.isArray(section.pages) ? section.pages : []) {
            if (typeof id !== 'string' || !slugPattern.test(id) || ids.has(id)) { fail(`Invalid/duplicate page ID: ${id}`); continue; }
            ids.add(id);
        }
    }
    if (!ids.has(nav.entry)) fail('Entry must be a listed page');
    const publicFiles = new Set();
    for (const id of ids) for (const locale of ['en', 'fa']) {
        const file = path.join(docs, locale, `${id}.md`);
        if (!fs.existsSync(file)) {
            (locale === 'en' ? errors : warnings).push(`Missing ${locale} translation: ${id}`);
            continue;
        }
        publicFiles.add(file);
        const text = read(file), front = text.match(/^---\r?\n([\s\S]*?)\r?\n---\r?\n/);
        if (!front) { fail(`Missing metadata: ${file}`); continue; }
        const metadata = {};
        for (const line of front[1].split(/\r?\n/)) {
            const m = line.match(/^(title|description):\s*(".*")\s*$/);
            if (!m || m[1] in metadata) { fail(`Invalid metadata line: ${file}: ${line}`); continue; }
            try { metadata[m[1]] = JSON.parse(m[2]); } catch { fail(`Invalid quoted YAML value: ${file}`); }
        }
        if (['title', 'description'].some(k => typeof metadata[k] !== 'string' || !metadata[k].trim())) fail(`Empty metadata: ${file}`);
        const body = unfence(text.slice(front[0].length));
        if ([...body.matchAll(/^ {0,3}#\s+\S.*$|^[^\n]+\n=+\s*$/gm)].length !== 1) fail(`Expected one body H1: ${file}`);
        if (/<\/?[a-zA-Z][^>]*>/.test(body.replace(/`[^`]*`/g, '').replace(/\]\(\s*<[^>]+>/g, ']('))) fail(`Raw HTML in public prose: ${file}`);
    }
    const markdown = [...walk(docs), ...fs.readdirSync(root).map(f => path.join(root, f))].filter(f => f.endsWith('.md') && fs.statSync(f).isFile());
    for (const file of markdown) {
        const text = unfence(read(file)).replace(/`+[^`\n]*`+/g, '');
        const references = new Map();
        for (const m of text.matchAll(/^ {0,3}\[([^\]]+)\]:\s*(<[^>]+>|\S+)(?:\s+["'(].*)?$/gm)) references.set(m[1].trim().toLowerCase(), m[2]);
        const targets = [...references.values()];
        // Destinations with balanced parentheses, angle paths and optional titles.
        for (const m of text.matchAll(/!?\[[^\]\n]*\]\(\s*(<[^>]+>|(?:[^\s()]|\([^()]*\))+)(?:\s+["'][^\n]*?["'])?\s*\)/g)) targets.push(m[1]);
        for (const m of text.matchAll(/!?\[([^\]\n]+)\]\[([^\]\n]*)\]/g)) {
            const key = (m[2] || m[1]).trim().toLowerCase();
            if (!references.has(key)) fail(`Undefined reference: ${file}: ${key}`);
        }
        for (let target of targets) {
            target = target.replace(/^<|>$/g, '');
            if (/^(?:[a-z][a-z0-9+.-]*:|\/\/)/i.test(target)) continue;
            const [rawPath, fragment] = target.split('#');
            let decoded;
            try { decoded = decodeURIComponent(rawPath.split('?')[0]); } catch { fail(`Invalid URL: ${file}: ${target}`); continue; }
            let dest = path.resolve(path.dirname(file), decoded || path.basename(file));
            if (!dest.startsWith(root + path.sep) && dest !== root) { fail(`Link escapes repository: ${file}: ${target}`); continue; }
            let fallback = false;
            if (!fs.existsSync(dest)) {
                const rel = path.relative(path.join(docs, 'fa'), dest).replaceAll(path.sep, '/').replace(/\.md$/, '');
                if (dest.startsWith(path.join(docs, 'fa') + path.sep) && ids.has(rel) && fs.existsSync(path.join(docs, 'en', rel + '.md'))) {
                    dest = path.join(docs, 'en', rel + '.md'); fallback = true;
                } else { fail(`Broken local reference: ${file}: ${target}`); continue; }
            }
            if (fragment && !fallback && dest.endsWith('.md')) {
                let anchor;
                try { anchor = decodeURIComponent(fragment); } catch { fail(`Invalid anchor encoding: ${file}: ${target}`); continue; }
                if (!anchors(read(dest).replace(/^---\n[\s\S]*?\n---\n/, '')).has(anchor)) fail(`Broken heading anchor: ${file}: ${target}`);
            }
        }
    }
    if (runtime) {
        const result = spawnSync('php', [path.join(root, 'scripts/docs-runtime.php')], { encoding: 'utf8' });
        if (result.status !== 0) fail(`Runtime inspection failed: ${result.stderr || result.error}`);
        else {
            try {
                const actual = JSON.parse(result.stdout), expected = JSON.parse(read(path.join(docs, 'contracts.json')));
                if (!same(actual, expected)) fail('Implementation contract changed: review docs/contracts.json and both translations; never refresh blindly');
                const labels = JSON.parse(read(path.join(docs, 'settings-labels.json')));
                const ui = [...walk(path.join(root, 'views')), ...walk(path.join(root, 'src'))].filter(f => f.endsWith('.php')).map(read).join('\n');
                const po = read(path.join(root, 'languages/persian-kit-fa_IR.po'));
                for (const locale of ['en', 'fa']) {
                    const settings = read(path.join(docs, locale, 'settings.md'));
                    for (const [key, module] of Object.entries(actual.modules)) {
                        const segment = settings.split(`\`${key}\``)[1]?.split('\n## ')[0] ?? '';
                        for (const [option, value] of Object.entries(module.defaults)) {
                            const label = option === 'enabled' ? module.label : labels[key]?.[option];
                            const row = segment.split('\n').find(l => l.startsWith(`| \`${option}\` |`)) ?? '';
                            if (!label || !row.includes(label) || !row.endsWith(`| \`${JSON.stringify(value)}\` |`)) fail(`Setting drift: ${locale} ${key}.${option}`);
                            if (!ui.includes(label.replaceAll("'", "\\'"))) fail(`Unknown UI label: ${label}`);
                            if (locale === 'fa' && label) {
                                const blocks = po.split('\n\n').filter(b => b.includes(`msgid ${JSON.stringify(label)}`));
                                const translated = blocks.flatMap(b => [...b.matchAll(/^msgstr (".*")$/gm)].map(m => JSON.parse(m[1]))).filter(Boolean);
                                if (translated.length && !translated.some(t => row.includes(t))) fail(`Persian label drift: ${key}.${option}`);
                            }
                        }
                    }
                    const api = read(path.join(docs, locale, 'developers/api.md'));
                    for (const name of Object.keys(actual.functions)) if (!api.includes(name)) fail(`Undocumented helper: ${locale} ${name}`);
                    const hookDoc = read(path.join(docs, locale, 'developers/hooks.md'));
                    const listed = [...hookDoc.matchAll(/^## (persian_kit_\w+)$/gm)].map(m => m[1]).sort();
                    if (!same(listed, Object.keys(actual.hooks))) fail(`Hook coverage drift: ${locale}`);
                    for (const [name, h] of Object.entries(actual.hooks)) {
                        const contract = hookDoc.split(`## ${name}\n`)[1]?.split('\n## ')[0] ?? '';
                        const signature = contract.match(/`(action|filter)` — `([^`]+)`/);
                        if (!signature || signature[1] !== h.kind || (signature[2] === '—' ? 0 : signature[2].split(',').length) !== h.arguments) fail(`Hook arguments drift: ${locale} ${name}`);
                    }
                    const install = read(path.join(docs, locale, 'installation.md'));
                    for (const key of ['Requires at least', 'Requires PHP', 'WC requires at least']) if (!install.includes(`**${actual.requirements[key]}+**`)) fail(`Requirement drift: ${locale} ${key}`);
                }
                for (const locale of ['en', 'fa']) {
                    const compatibility = read(path.join(docs, locale, 'compatibility.md'));
                    for (const version of actual.coverage.versions) if (!compatibility.includes(version)) fail(`CI coverage drift: ${locale} ${version}`);
                }
                const version = actual.requirements.Version;
                if (JSON.parse(read(path.join(root, 'package.json'))).version !== version || !read(path.join(root, 'src/constants.php')).includes(`'${version}'`)) fail('Source version identifiers disagree');
                for (const file of ['README.md', 'README.fa.md', 'docs/en/compatibility.md', 'docs/fa/compatibility.md']) if (!read(path.join(root, file)).includes(`**${version}**`)) fail(`Version drift: ${file}`);
                if (!read(path.join(root, 'readme.txt')).includes(`Stable tag: ${version}`)) fail('WordPress stable tag drift');
            } catch (e) { fail(`Contract check: ${e.message}`); }
        }
    }
    return { errors, warnings, pages: publicFiles.size, markdown: markdown.length };
}
export function examples(root = repository) {
    const errors = []; let syntax = 0, executed = 0;
    const temp = fs.mkdtempSync(path.join(os.tmpdir(), 'persian-kit-docs-'));
    try {
        const files = [...walk(path.join(root, 'docs')), ...['README.md', 'README.fa.md'].map(f => path.join(root, f))].filter(f => f.endsWith('.md'));
        for (const file of files) {
            const text = read(file);
            for (const match of text.matchAll(/^```(php|bash|sh)\s*\n([\s\S]*?)^```\s*$/gm)) {
                const [, language, code] = match;
                const tmp = path.join(temp, language === 'php' ? 'example.php' : 'example.sh');
                const source = language === 'php' && !code.includes('<?php') ? '<?php\n' + code : code;
                fs.writeFileSync(tmp, source);
                const result = spawnSync(language === 'php' ? 'php' : 'bash', [language === 'php' ? '-l' : '-n', tmp], { encoding: 'utf8' });
                syntax++;
                if (result.status !== 0) errors.push(`${file}: ${result.stderr || result.stdout}`);
                const next = text.slice(match.index + match[0].length).match(/^\s*```text\n([\s\S]*?)\n```/);
                if (language === 'php' && next) {
                    // Only explicit PHP/exact-output pairs are executable; contextual snippets are lint-only.
                    const run = spawnSync('php', ['-d', 'display_errors=stderr', '-d', `auto_prepend_file=${path.join(root, 'scripts/docs-example-bootstrap.php')}`, tmp], { encoding: 'utf8', timeout: 10000 });
                    executed++;
                    if (run.status !== 0 || run.stderr || run.stdout.replace(/\r\n/g, '\n') !== next[1] + '\n') errors.push(`${file}: deterministic output mismatch\n${run.stderr || run.stdout}`);
                }
            }
        }
    } finally { fs.rmSync(temp, { recursive: true, force: true }); }
    return { errors, syntax, executed };
}
if (process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
    const result = process.argv.includes('--examples') ? examples() : validate();
    for (const warning of result.warnings ?? []) console.warn(`WARNING: ${warning}`);
    for (const error of result.errors) console.error(`ERROR: ${error}`);
    console.log(JSON.stringify({ ...result, errors: result.errors.length, warnings: result.warnings?.length }));
    process.exitCode = result.errors.length ? 1 : 0;
}
