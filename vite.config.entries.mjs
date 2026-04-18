import { defineConfig } from 'vite';
import { resolve } from 'path';

const entry = process.env.ENTRY || 'admin';

export default defineConfig({
    publicDir: false,
    build: {
        lib: {
            entry: resolve(__dirname, `resources/entries/${entry}-entry.js`),
            formats: ['iife'],
            name: `persianKit_${entry}`,
            fileName: () => `${entry}.js`,
        },
        outDir: resolve(__dirname, 'public/js'),
        emptyOutDir: false,
        minify: false,
        sourcemap: false,
    },
});
