# ADR-0024: Obol is an installable PWA (manifest only, no offline)

- Status: Accepted
- Date: 2026-07-22

## Context

The out-of-scope register listed "PWA / mobile-first / native app - web UI is the only target"
among the deliberate non-goals. That entry predates the responsive-design work: Obol is now being
made genuinely usable on a phone, and part of that is letting a user add it to their home screen
so it launches like an app instead of a browser tab.

Reopening a recorded non-goal is done deliberately, with this ADR, per the register's own rule
rather than by quietly adding the feature.

Two facts bound the scope:

- **Installability needs almost nothing.** A web app manifest (`name`, `start_url`, `display:
  standalone`, icons at 192 and 512 including a maskable pair) plus a filled-tile
  `apple-touch-icon` and the `apple-mobile-web-app-*` meta tags is enough for iOS "Add to Home
  Screen" and, on modern Chrome (106+), Android install - neither requires a service worker.
- **Offline is a different, larger commitment.** A service worker brings a caching strategy that
  has to stay reconciled with AssetMapper's content-digested asset URLs, plus update/versioning
  handling. Obol is a live data app; nothing about it needs to work offline today.

The coin favicon (`assets/icons/obol-coin.svg`) is a bare circle on transparency, which iOS
renders on black and Android floats on the launcher. A home-screen icon needs an opaque tile.

## Decision

**Obol ships as an installable PWA at the manifest level. A service worker and offline support
are explicitly out of scope for now.**

- `public/manifest.webmanifest` declares `display: standalone`, `start_url: /app`, `scope: /`, a
  `theme_color`/`background_color` drawn from the palette, and four icons (192/512, each `any` and
  `maskable`). It is served straight from the web root by Caddy, ahead of Symfony, with an explicit
  `application/manifest+json` Content-Type (Caddy's MIME table has no `.webmanifest` entry).
- The icons render from the existing coin SVG onto a solid dark tile (`--obol-surface`, `#1f1810`)
  via `bin/generate-icons.mjs` / `mise run icons`. Maskable variants keep the coin inside the ~80%
  safe zone; the `apple-touch-icon` moves onto the same tile so it no longer shows black on iOS.
- The base layout head gains the manifest link, per-scheme `theme-color` metas matching the header
  band, and the `mobile-web-app-capable` / `apple-mobile-web-app-*` tags. Pinch-zoom stays enabled
  (no `user-scalable=no`); the status-bar style is `black`.

### What is deferred

- **Service worker + offline caching.** Its own future issue. When taken on, it must handle
  AssetMapper's digested URLs and an update strategy rather than a naive cache-all.
- **Per-scheme install icons.** The installed home-screen icon cannot live-switch with the OS
  theme (the manifest has no shipped light/dark icon mechanism, and iOS fixes the icon at
  add-to-home-screen time), so a single tile that reads well in both themes is used deliberately.

## Consequences

- A user can install Obol to their home screen on iOS and Android and launch it standalone, with no
  browser chrome and a themed status bar - the "native-app-ish" feel the responsive work targets.
- The app still works only online; there is no offline mode or background sync, and that is a
  recorded deferral, not an oversight.
- The icon set is a committed artifact regenerated only when the mark changes, consistent with how
  the favicons were already handled.
