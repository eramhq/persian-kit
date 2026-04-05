import { cpSync, mkdirSync } from 'node:fs';
import { resolve, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

const copies = [
    // Vazirmatn variable font (Arabic subset covers Persian)
    {
        src: 'node_modules/@fontsource-variable/vazirmatn/files/vazirmatn-arabic-wght-normal.woff2',
        dest: 'public/fonts/vazirmatn/Vazirmatn[wght].woff2',
    },
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
];

for (const { src, dest } of copies) {
    const srcPath = resolve(root, src);
    const destPath = resolve(root, dest);

    mkdirSync(dirname(destPath), { recursive: true });
    cpSync(srcPath, destPath);

    console.log(`  ${src} -> ${dest}`);
}

console.log('Assets copied successfully.');
