// ABOUTME: Vitest config for the Stimulus controller unit tests.
// ABOUTME: jsdom environment; only our assets/ tests run, never the vendored importmap code.

import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

const stub = (path) => fileURLToPath(new URL(path, import.meta.url));

export default defineConfig({
    // AssetMapper vendors third-party packages outside node_modules, so Vite cannot resolve them for
    // tests. Alias the ones our controllers import to resolvable stubs (tests mock behavior via vi.mock).
    resolve: {
        alias: [
            { find: /^driver\.js\/dist\/driver\.css$/, replacement: stub('./assets/test/stubs/empty-css.js') },
            { find: /^driver\.js$/, replacement: stub('./assets/test/stubs/driver.js') },
        ],
    },
    test: {
        environment: 'jsdom',
        include: ['assets/**/*.test.js'],
        exclude: ['assets/vendor/**', 'assets/test/**', 'node_modules/**'],
    },
});
