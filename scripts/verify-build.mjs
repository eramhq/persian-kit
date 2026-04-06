import { existsSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

const requiredOutputs = [
    'public/css/admin.css',
    'public/css/admin-font.css',
    'public/css/gutenberg-jalali.css',
    'public/js/admin.min.js',
    'public/js/admin-date-override.js',
    'public/js/gutenberg-jalali-panel.js',
    'public/js/gutenberg-zwnj.js',
    'public/js/jalali.js',
    'public/js/text-editor-zwnj.js',
    'public/js/tinymce-zwnj.js',
    'public/js/woocommerce-date-fields.js',
    'public/fonts/vazirmatn/vazirmatn-arabic-wght-normal.woff2',
    'public/fonts/vazirmatn/vazirmatn-latin-wght-normal.woff2',
    'public/fonts/vazirmatn/vazirmatn-latin-ext-wght-normal.woff2',
];

const missing = requiredOutputs.filter((output) => !existsSync(resolve(root, output)));

if (missing.length > 0) {
    console.error('Build verification failed. Missing generated assets:');
    for (const output of missing) {
        console.error(`  - ${output}`);
    }
    process.exit(1);
}

console.log('Build verification passed.');
