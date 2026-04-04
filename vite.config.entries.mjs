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
            fileName: () => `${entry}.min.js`,
        },
        outDir: resolve(__dirname, 'public/js'),
        emptyOutDir: false,
        minify: 'esbuild',
        sourcemap: false,
    },
});
