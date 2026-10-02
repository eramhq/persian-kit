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
    // Vazirmatn licence (SIL OFL 1.1 must travel with the font files)
    {
        src: 'node_modules/@fontsource-variable/vazirmatn/LICENSE',
        dest: 'public/fonts/vazirmatn/OFL.txt',
    },
    // Noto Sans Arabic variable font subsets (arabic, latin, latin-ext) and licence
    ...['arabic', 'latin', 'latin-ext'].map(subset => ({
        src: `node_modules/@fontsource-variable/noto-sans-arabic/files/noto-sans-arabic-${subset}-wght-normal.woff2`,
        dest: `public/fonts/noto-sans-arabic/noto-sans-arabic-${subset}-wght-normal.woff2`,
    })),
    {
        src: 'node_modules/@fontsource-variable/noto-sans-arabic/LICENSE',
        dest: 'public/fonts/noto-sans-arabic/OFL.txt',
    },
    // IBM Plex Sans Arabic has no variable version: the static weights the admin uses, and licence
    ...['arabic', 'latin', 'latin-ext'].flatMap(subset => [400, 500, 600, 700].map(weight => ({
        src: `node_modules/@fontsource/ibm-plex-sans-arabic/files/ibm-plex-sans-arabic-${subset}-${weight}-normal.woff2`,
        dest: `public/fonts/ibm-plex-sans-arabic/ibm-plex-sans-arabic-${subset}-${weight}-normal.woff2`,
    }))),
    {
        src: 'node_modules/@fontsource/ibm-plex-sans-arabic/LICENSE',
        dest: 'public/fonts/ibm-plex-sans-arabic/OFL.txt',
    },
    // Admin CSS (settings page styles)
    {
        src: 'resources/css/admin.css',
        dest: 'public/css/admin.css',
    },
    // Persian Kit logo (settings page header and footer)
    {
        src: 'resources/images/logo.svg',
        dest: 'public/images/logo.svg',
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
    // Jalali conversion library (block editor date editor)
    {
        src: 'resources/js/jalali.js',
        dest: 'public/js/jalali.js',
    },
    // Post date picker (Classic Editor publish box + Quick Edit)
    {
        src: 'resources/js/classic-date-fields.js',
        dest: 'public/js/classic-date-fields.js',
    },
    // Gutenberg Jalali date editor
    {
        src: 'resources/js/gutenberg-jalali-date.js',
        dest: 'public/js/gutenberg-jalali-date.js',
    },
    // WooCommerce admin Jalali date fields
    {
        src: 'resources/js/woocommerce-date-fields.js',
        dest: 'public/js/woocommerce-date-fields.js',
    },
    // WooCommerce classic checkout city dropdown
    {
        src: 'resources/js/woocommerce-city-select.js',
        dest: 'public/js/woocommerce-city-select.js',
    },
    // WooCommerce block cart and checkout Persian price digits
    {
        src: 'resources/js/woocommerce-block-prices.js',
        dest: 'public/js/woocommerce-block-prices.js',
    },
    // Iranian cities by WooCommerce state code
    {
        src: 'resources/data/ir-cities.json',
        dest: 'public/data/ir-cities.json',
    },
    // Jalali date picker adapter for form fields
    {
        src: 'resources/js/date-field.js',
        dest: 'public/js/date-field.js',
    },
    {
        src: 'resources/css/date-field.css',
        dest: 'public/css/date-field.css',
    },
    // Licences of the libraries bundled into public/js/datepicker.js
    {
        src: 'node_modules/intl-datepicker/LICENSE',
        dest: 'public/licenses/intl-datepicker.txt',
    },
    {
        src: 'node_modules/@internationalized/date/LICENSE',
        dest: 'public/licenses/internationalized-date.txt',
    },
    // Media library grid Jalali month filter
    {
        src: 'resources/js/media-grid-date-filter.js',
        dest: 'public/js/media-grid-date-filter.js',
    },
    // Gutenberg Jalali date editor styles
    {
        src: 'resources/css/gutenberg-jalali.css',
        dest: 'public/css/gutenberg-jalali.css',
    },
    // WooCommerce orders Jalali month filter styles
    {
        src: 'resources/css/woo-order-filter.css',
        dest: 'public/css/woo-order-filter.css',
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
