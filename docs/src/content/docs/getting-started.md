---
title: "Getting Started"
---

This guide covers setting up Obol for local development.

## Prerequisites

- **Docker** with the Compose plugin. Any compatible engine works; [OrbStack](https://orbstack.dev/) is recommended on macOS for its faster startup and volume mounts.
- **[mise](https://mise.jdx.dev/)** for task shortcuts.
- **Node.js 24+ with npm** for the dev-only JS toolchain (Biome + Vitest + `tsc --checkJs`). Needed to run the JS checks and the git hooks; not part of the app runtime.
- **[Lolly](https://code.dev88.work/dev88/lolly)** — the shared local dev proxy. Optional but recommended; gives you `https://obol.lolly.localhost` with browser-trusted TLS.

The docs site needs no extra host tooling — it builds in its own Docker container (see [Working on documentation](#working-on-documentation) below).

No PHP, Composer, or Postgres install on the host is required — everything PHP runs inside Docker. Node is the one host-side exception, used only by the dev/CI JS toolchain (see [Frontend](frontend.md#javascript-toolchain-dev-only)).

## Setup

### 1. Clone and start the stack

```bash
git clone ssh://git@code.dev88.work:222/dev88/obol.git
cd obol
mise run up
```

- With Lolly running: the app is served at **https://obol.lolly.localhost** with browser-trusted TLS.
- With Lolly stopped: the app is served at `http://127.0.0.1:8080` (plain HTTP, for quick "is it alive" checks).

`bin/dc` auto-detects which mode to use by probing for Lolly's `lolly` Docker network — no flag.

Migrations run automatically inside the container on startup. No fixtures are loaded by default — run `mise run seed` if you want sample data.

### 2. Install the JS toolchain

```bash
npm ci
```

Installs the dev-only JS devDependencies (the `composer install` equivalent for JS). Required for `mise run check`, the git hooks, and the `js:*` tasks. Nothing here is bundled or shipped to the browser.

## Verify the setup

```bash
mise run test              # run the PHPUnit suite in the container
mise run js:test           # run the Vitest suite (host-side)
mise run dce -- php bin/console about   # Symfony info dump
```

## Working on documentation

The docs are an [Astro Starlight](https://starlight.astro.build/) site under `docs/`, built in a Dockerized pnpm container — no Node or other host tooling required.

```bash
mise run docs:install   # one-time after clone (installs deps in the docs container)
mise run docs:dev       # live preview at http://localhost:4321/obol/
mise run docs:build     # build to docs/dist/ (runs the links validator)
```

The separate end-user guide under `docs-user/` works the same way via the `docs-user:*` tasks.

## Further reading

- [Lolly's runbook](https://code.dev88.work/dev88/lolly/src/branch/main/docs/agents/integrate-an-app.md) — the full contract for shared-mode routing and worktree-per-hostname
- [Mise Tasks](development/mise-tasks.md) — full task reference
- [Deployment](deployment.md) — production Docker setup
