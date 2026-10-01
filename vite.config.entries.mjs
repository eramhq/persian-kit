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
        rollupOptions: {
            output: {
                // Alpine.js ships without a licence header; keep its MIT notice in the bundle.
                banner: `/*!
 * Includes Alpine.js (https://alpinejs.dev)
 * Copyright (c) 2019-2025 Caleb Porzio and contributors
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this software
 * and associated documentation files (the "Software"), to deal in the Software without restriction,
 * including without limitation the rights to use, copy, modify, merge, publish, distribute,
 * sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all copies or
 * substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT
 * NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
 * NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM,
 * DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 */`,
            },
        },
        outDir: resolve(__dirname, 'public/js'),
        emptyOutDir: false,
        minify: false,
        sourcemap: false,
    },
});
