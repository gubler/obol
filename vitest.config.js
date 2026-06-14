// ABOUTME: Vitest config for the Stimulus controller unit tests.
// ABOUTME: jsdom environment; only our assets/ tests run, never the vendored importmap code.

import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        environment: 'jsdom',
        include: ['assets/**/*.test.js'],
        exclude: ['assets/vendor/**', 'node_modules/**'],
    },
});
