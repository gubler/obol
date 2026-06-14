# Frontend

Obol uses Symfony AssetMapper for a zero-build-step frontend. JavaScript is served as native ES modules via the importmap standard, and CSS is handled by Tailwind v4.

## AssetMapper

No webpack, no Vite, and no Node.js in the runtime or build pipeline. Symfony AssetMapper maps logical module names to file paths and generates an importmap for the browser. (Node is used only by the dev-only [JavaScript toolchain](#javascript-toolchain-dev-only) below - nothing it produces is bundled or shipped.)

The importmap is defined in `importmap.php` at the repo root. Key entries:

| Module | Version | Purpose |
|--------|---------|---------|
| `app` | local | Main entrypoint (`assets/app.js`) |
| `@hotwired/stimulus` | 3.2.2 | Stimulus framework |
| `@hotwired/turbo` | 8.0.20 | Turbo Drive for SPA-like navigation |
| `@tailwindplus/elements` | 1.0.18 | Pre-built UI components |
| `tailwindcss/*` | 4.1.17 | Tailwind CSS utilities |

### Adding new JS dependencies

```bash
php bin/console importmap:require package-name
```

This downloads the package and adds it to `importmap.php`.

### Asset compilation for production

Three commands, in order:

```bash
php bin/console importmap:install
php bin/console tailwind:build
php bin/console asset-map:compile
```

These are run automatically in the Dockerfile builder stage and in CI.

## Stimulus

[Hotwired Stimulus](https://stimulus.hotwired.dev/) provides lightweight JavaScript controllers that attach behavior to HTML via `data-controller` attributes.

**Bootstrap:** `assets/bootstrap.js` calls `startStimulusApp()` from `@symfony/stimulus-bundle`, which auto-discovers controllers.

**Custom controllers** live in `assets/controllers/`:

- `composition_pie_controller.js` — enriches a ux-chartjs pie tooltip with the display amount and native split
- `conditional_field_controller.js` — reveals a dependent field only while a trigger checkbox is checked (progressive enhancement)
- `obligation_trend_controller.js` — formats the obligation-trend line chart's y-axis and tooltip as money
- `csrf_protection_controller.js` — CSRF token handling (recipe-shipped by Symfony)

**Controller registration** is configured in `assets/controllers.json`, which also enables the Turbo UX bundle controller.

### Adding a new Stimulus controller

Create a file in `assets/controllers/` following the naming convention `{name}_controller.js`. It is auto-registered by the Stimulus bundle — no manual import needed.

## JavaScript toolchain (dev-only)

The Stimulus controllers get the same three guard rails as the PHP code, mirroring the PHP stack one-for-one. None of it ships to the browser - AssetMapper + importmap stay the runtime; this is Node tooling that runs only at dev/CI time. A `package.json` declares the devDependencies and a `package-lock.json` is committed; run `npm ci` once after pulling (the `composer install` equivalent for JS).

| Layer | Tool | PHP analog | Config |
|-------|------|-----------|--------|
| Code style + lint | [Biome](https://biomejs.dev/) | PHP CS Fixer | `biome.json` |
| Unit tests | [Vitest](https://vitest.dev/) + jsdom | Pest | `vitest.config.js` |
| Static analysis | `tsc --checkJs` | PHPStan | `jsconfig.json` |

Local commands (host-side, via npm):

```bash
mise run js:cs         # Biome: auto-fix style + lint
mise run js:cs:check   # Biome: check only (CI parity)
mise run js:test       # Vitest unit tests
mise run js:sa         # tsc --checkJs type-check
```

All three are folded into `mise run check`, the git hooks, and CI alongside the PHP checks.

**Biome** is scoped to `assets/**/*.js` and configured to match the existing house style (4-space indent, single quotes, semicolons). It excludes the vendored importmap code (`assets/vendor/`) and the recipe-shipped `csrf_protection_controller.js`, which is treated like vendored scaffolding.

**Vitest** runs in a jsdom environment and exercises controllers through a real Stimulus `Application` (mount on a fixture, drive the DOM, assert behavior) rather than calling private methods. Spec files are named `*.test.js` and live next to the controller they cover; `config/packages/asset_mapper.yaml` excludes that pattern so specs never compile into the asset map.

**`tsc --checkJs`** type-checks the plain `.js` with no conversion to TypeScript. Two `.d.ts` files under `types/` keep it proportional: `stimulus.d.ts` widens the Stimulus `Controller` with an index signature so the runtime-injected `fooTarget` / `fooValue` members don't drown out real errors, and `importmap-modules.d.ts` stubs the importmap-only specifiers (`@symfony/stimulus-bundle`, `@tailwindplus/elements`) that resolve at runtime rather than from `node_modules`. This is a baseline to ratchet stricter over time, the same way the PHP coverage threshold is.

## Turbo

[Hotwired Turbo](https://turbo.hotwired.dev/) intercepts link clicks and form submissions, replacing full page loads with fetch requests that swap the `<body>` content. This gives SPA-like speed without writing JavaScript.

Turbo Drive is enabled by default via the `@hotwired/turbo` import. Configuration is in `config/packages/ux_turbo.yaml`.

## Tailwind CSS v4

Tailwind is managed by `symfonycasts/tailwind-bundle`, which ships a standalone Tailwind binary (no Node.js required). The pinned version is configured in `config/packages/symfonycasts_tailwind.yaml`.

**CSS entry point:** `assets/styles/app.css`

**Local development:** Tailwind compiles CSS on-the-fly. No watch process needed unless you want faster recompilation:

```bash
php bin/console tailwind:watch
```

**Production:** `php bin/console tailwind:build` generates the optimized CSS.

## Templates

Twig templates live in `templates/` and extend `base.html.twig`, which provides:

- Dark navigation header with links to Subscriptions and Categories
- Flash message rendering (success, warning, error, notice)
- Main content block with a white card layout

Template organization:

```
templates/
├── base.html.twig
├── category/
│   ├── index.html.twig
│   ├── show.html.twig
│   ├── new.html.twig
│   └── edit.html.twig
├── subscription/
│   ├── index.html.twig
│   ├── show.html.twig
│   ├── new.html.twig
│   └── edit.html.twig
└── payment/
    └── new.html.twig
```
