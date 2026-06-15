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
| Unit tests | [Vitest](https://vitest.dev/) + jsdom | PHPUnit | `vitest.config.js` |
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

**Local development:** a dev-only `tailwind` sidecar container (defined in `compose.override.yaml`)
runs `tailwind:build --watch --poll` against the shared `tailwind_build` volume, so the compiled CSS
rebuilds automatically when `mise run up` brings the stack up and on every template or CSS edit - no
manual build step. `--poll` is required because Docker Desktop filesystem events do not cross the bind
mount reliably on macOS. The sidecar shares `var/tailwind` with the `php` container so the
`app.built.css` it writes is the file the app serves.

- `mise run tailwind:restart` - restart the watcher (e.g. after changing its config); reports a
  friendly message if the stack is not up.
- `mise run tailwind` - one-shot rebuild, kept for CI and scripted builds.

**Production:** `php bin/console tailwind:build --minify` generates the optimized CSS in the image
builder stage; `asset-map:compile` then bakes content-hashed copies into `public/assets/`. No watcher
runs in prod.

**Form field styling.** Forms use `templates/form/obol_layout.html.twig` (wired in
`config/packages/twig.yaml`), a thin theme that `{% use %}`s Symfony's `tailwind_2_layout`
and recolors the label, help-text, and error blocks with the tokens (`text-fg`,
`text-fg-muted`, `text-danger`) - the vendor theme hardcodes gray/red there. Two things in
`assets/styles/app.css` make the inputs themselves look like fields, and both are required:

- `@plugin "@tailwindcss/forms"` supplies the input chrome (border, ring, rounded corners,
  padding). The theme itself only puts layout classes (`mt-1 w-full`) on inputs; without the
  plugin, Tailwind's Preflight strips inputs' native border and they render invisible. The
  standalone Tailwind binary bundles the first-party plugins, so no npm install is needed.
- `@source ".../tailwind_2_layout.html.twig"` keeps the theme's own utility classes (labels,
  help text, error states) from being purged. The theme lives under `vendor/`, which Tailwind
  v4's automatic source detection skips, so any Tailwind classes that appear only inside
  `vendor/` need an explicit `@source`.

The forms plugin paints controls with hardcoded gray/blue, which would not follow the
theme. An unlayered block in `app.css` re-colors inputs, selects, textareas, checkboxes,
and radios with the design tokens (`--obol-surface` / `--obol-line` / `--obol-fg`, brand
focus ring) so form fields track light/dark like everything else; it is unlayered so it
wins over the plugin's base-layer rules. `:root` / `.dark` also set `color-scheme` so native
controls (the date picker, scrollbars) match the active scheme.

The forms plugin leaves `input[type=file]` borderless, so the subscription form theme
(`templates/form/_subscription_form_theme.html.twig`) overrides `file_widget` to give it a
bordered box and a styled Browse button, and `tile_color_widget` to render the color choice
as swatch chips with a brand ring on the selected one. Both are token-styled. The `file_widget`
override delegates with `{{ block('form_widget_simple') }}` rather than `{{ parent() }}` - a
standalone form theme file does not `{% use %}` a base template, so `parent()` would throw.

The compiled CSS is written to `var/tailwind/app.built.css` **inside the container**; that path
is a container-only volume, so its host-side copy is stale and not what gets served. To inspect
the real output, read it from the container (`bin/dc exec -T php cat var/tailwind/app.built.css`)
or fetch the served asset over HTTP.

## Branding and favicons

The app's mark is a heraldic bee struck on a beaded gold coin (an obol). The single source of truth is the SVG:

**`assets/icons/obol-coin.svg`** — served through AssetMapper as both the header logo and the SVG favicon (`<link rel="icon" type="image/svg+xml">` in `base.html.twig`).

SVG favicons aren't honored everywhere (notably Safari and iOS), so a small PNG set lives at the web root for fallback and is wired up in `base.html.twig`:

- `public/favicon-32.png`, `public/favicon-16.png` — PNG `rel="icon"` fallbacks
- `public/apple-touch-icon.png` (180px) — iOS home screen / Safari

Those PNGs are generated from the SVG, not drawn by hand:

```bash
mise run icons   # rasterize assets/icons/obol-coin.svg -> public/*.png
```

The task ([`bin/generate-icons.mjs`](../bin/generate-icons.mjs), host-side via `sharp`) renders the 180px tile from the full coin, and derives a flat-rim variant on the fly for the 16/32px icons — the beaded rim turns to noise that small. Re-run it after editing the SVG and commit the refreshed PNGs. It is intentionally **not** part of `mise run check`, the hooks, or CI: the outputs are committed artifacts, regenerated only when the mark changes.

The same `obol-coin.svg` is the Dashy homelab tile (tracked in the homelab repo).

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
