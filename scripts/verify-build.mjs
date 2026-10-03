import { existsSync, statSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

const requiredOutputs = [
    'public/css/admin.css',
    'public/css/admin-font.css',
    'public/css/date-field.css',
    'public/css/gutenberg-jalali.css',
    'public/css/woo-order-filter.css',
    'public/js/admin.js',
    'public/js/classic-date-fields.js',
    'public/js/date-field.js',
    'public/js/datepicker.js',
    'public/js/forminator-digits.js',
    'public/js/gutenberg-jalali-date.js',
    'public/js/gutenberg-zwnj.js',
    'public/js/jalali.js',
    'public/js/media-grid-date-filter.js',
    'public/js/text-editor-zwnj.js',
    'public/js/tinymce-zwnj.js',
    'public/js/woocommerce-block-prices.js',
    'public/js/woocommerce-city-select.js',
    'public/js/woocommerce-date-fields.js',
    'public/data/ir-cities.json',
    'public/images/logo.svg',
    'public/fonts/vazirmatn/OFL.txt',
    'public/fonts/noto-sans-arabic/OFL.txt',
    'public/fonts/ibm-plex-sans-arabic/OFL.txt',
    'public/licenses/intl-datepicker.txt',
    'public/licenses/internationalized-date.txt',
    'public/fonts/vazirmatn/vazirmatn-arabic-wght-normal.woff2',
    'public/fonts/vazirmatn/vazirmatn-latin-wght-normal.woff2',
    'public/fonts/vazirmatn/vazirmatn-latin-ext-wght-normal.woff2',
    'public/fonts/noto-sans-arabic/noto-sans-arabic-arabic-wght-normal.woff2',
    'public/fonts/noto-sans-arabic/noto-sans-arabic-latin-wght-normal.woff2',
    'public/fonts/noto-sans-arabic/noto-sans-arabic-latin-ext-wght-normal.woff2',
    'public/fonts/ibm-plex-sans-arabic/ibm-plex-sans-arabic-arabic-400-normal.woff2',
    'public/fonts/ibm-plex-sans-arabic/ibm-plex-sans-arabic-arabic-500-normal.woff2',
    'public/fonts/ibm-plex-sans-arabic/ibm-plex-sans-arabic-arabic-600-normal.woff2',
    'public/fonts/ibm-plex-sans-arabic/ibm-plex-sans-arabic-arabic-700-normal.woff2',
    'public/fonts/ibm-plex-sans-arabic/ibm-plex-sans-arabic-latin-400-normal.woff2',
    'public/fonts/ibm-plex-sans-arabic/ibm-plex-sans-arabic-latin-500-normal.woff2',
    'public/fonts/ibm-plex-sans-arabic/ibm-plex-sans-arabic-latin-600-normal.woff2',
    'public/fonts/ibm-plex-sans-arabic/ibm-plex-sans-arabic-latin-700-normal.woff2',
    'public/fonts/ibm-plex-sans-arabic/ibm-plex-sans-arabic-latin-ext-400-normal.woff2',
    'public/fonts/ibm-plex-sans-arabic/ibm-plex-sans-arabic-latin-ext-500-normal.woff2',
    'public/fonts/ibm-plex-sans-arabic/ibm-plex-sans-arabic-latin-ext-600-normal.woff2',
    'public/fonts/ibm-plex-sans-arabic/ibm-plex-sans-arabic-latin-ext-700-normal.woff2',
];

const missing = requiredOutputs.filter((output) => !existsSync(resolve(root, output)));

if (missing.length > 0) {
    console.error('Build verification failed. Missing generated assets:');
    for (const output of missing) {
        console.error(`  - ${output}`);
    }
    process.exit(1);
}

// Size budgets, in bytes. The date picker bundle is about 137 kB with only
// the Persian calendar; importing intl-datepicker/full adds every calendar.
const budgets = {
    'public/js/datepicker.js': 160 * 1024,
};

const oversized = Object.entries(budgets).filter(([output, limit]) => statSync(resolve(root, output)).size > limit);

if (oversized.length > 0) {
    console.error('Build verification failed. Assets over their size budget:');
    for (const [output, limit] of oversized) {
        console.error(`  - ${output}: ${statSync(resolve(root, output)).size} bytes, budget ${limit}`);
    }
    process.exit(1);
}

console.log('Build verification passed.');
