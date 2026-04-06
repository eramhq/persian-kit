import { cpSync, mkdirSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

const copies = [
    // Vazirmatn variable font subsets (arabic, latin, latin-ext)
    ...['arabic', 'latin', 'latin-ext'].map(subset => ({
        src: `node_modules/@fontsource-variable/vazirmatn/files/vazirmatn-${subset}-wght-normal.woff2`,
        dest: `public/fonts/vazirmatn/vazirmatn-${subset}-wght-normal.woff2`,
    })),
    // Admin CSS (settings page styles)
    {
        src: 'resources/css/admin.css',
        dest: 'public/css/admin.css',
    },
    // Admin font CSS
    {
        src: 'resources/css/admin-font.css',
        dest: 'public/css/admin-font.css',
    },
    // TinyMCE ZWNJ plugin
    {
        src: 'resources/js/tinymce-zwnj.js',
        dest: 'public/js/tinymce-zwnj.js',
    },
    // Text editor ZWNJ handler
    {
        src: 'resources/js/text-editor-zwnj.js',
        dest: 'public/js/text-editor-zwnj.js',
    },
    // Gutenberg ZWNJ handler
    {
        src: 'resources/js/gutenberg-zwnj.js',
        dest: 'public/js/gutenberg-zwnj.js',
    },
    // Jalali conversion library
    {
        src: 'resources/js/jalali.js',
        dest: 'public/js/jalali.js',
    },
    // Admin date override (Quick Edit + Classic Editor)
    {
        src: 'resources/js/admin-date-override.js',
        dest: 'public/js/admin-date-override.js',
    },
    // Gutenberg Jalali date editor
    {
        src: 'resources/js/gutenberg-jalali-panel.js',
        dest: 'public/js/gutenberg-jalali-panel.js',
    },
    // WooCommerce admin Jalali date fields
    {
        src: 'resources/js/woocommerce-date-fields.js',
        dest: 'public/js/woocommerce-date-fields.js',
    },
    // Gutenberg Jalali date editor styles
    {
        src: 'resources/css/gutenberg-jalali.css',
        dest: 'public/css/gutenberg-jalali.css',
    },
];

for (const { src, dest } of copies) {
    const srcPath = resolve(root, src);
    const destPath = resolve(root, dest);

    mkdirSync(dirname(destPath), { recursive: true });
    cpSync(srcPath, destPath);

    console.log(`  ${src} -> ${dest}`);
}

console.log('Assets copied successfully.');
