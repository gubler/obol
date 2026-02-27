# Frontend

Obol uses Symfony AssetMapper for a zero-build-step frontend. JavaScript is served as native ES modules via the importmap standard, and CSS is handled by Tailwind v4.

## AssetMapper

No webpack, no Vite, no Node.js. Symfony AssetMapper maps logical module names to file paths and generates an importmap for the browser.

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

- `csrf_protection_controller.js` — CSRF token handling
- `hello_controller.js` — boilerplate example

**Controller registration** is configured in `assets/controllers.json`, which also enables the Turbo UX bundle controller.

### Adding a new Stimulus controller

Create a file in `assets/controllers/` following the naming convention `{name}_controller.js`. It is auto-registered by the Stimulus bundle — no manual import needed.

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
