# Getting Started

This guide covers setting up Obol for local development.

## Prerequisites

- **Docker** with the Compose plugin. Any compatible engine works; [OrbStack](https://orbstack.dev/) is recommended on macOS for its faster startup and volume mounts.
- **[mise](https://mise.jdx.dev/)** for task shortcuts.
- **[Lolly](https://code.dev88.work/dev88/lolly)** — the shared local dev proxy. Optional but recommended; gives you `https://obol.lolly.localhost` with browser-trusted TLS.
- **[MkDocs Material](https://squidfunk.github.io/mkdocs-material/)** (optional, for editing the docs site).

No PHP, Composer, or Postgres install on the host is required — everything runs inside Docker.

## Setup

### 1. Clone and start the stack

```bash
git clone ssh://git@code.dev88.work:222/dev88/obol.git
cd obol
mise run up
```

- With Lolly running: the app is served at **https://obol.lolly.localhost** with browser-trusted TLS.
- With Lolly stopped: the app is served at **http://127.0.0.1:8080** (plain HTTP, for quick "is it alive" checks).

`bin/dc` auto-detects which mode to use by probing for Lolly's `lolly` Docker network — no flag.

Migrations run automatically inside the container on startup. No fixtures are loaded by default — run `mise run seed` if you want sample data.

## Verify the setup

```bash
mise run test              # run the Pest suite in the container
mise run dce -- php bin/console about   # Symfony info dump
```

## Working on documentation

The docs site uses MkDocs with the Material theme. Install it on the host with:

```bash
pipx install mkdocs-material
```

Then:

```bash
mise run docs:serve    # live preview at http://127.0.0.1:8000
mise run docs:build    # output to site/
```

## Further reading

- [Lolly's runbook](https://code.dev88.work/dev88/lolly/src/branch/main/docs/agents/integrate-an-app.md) — the full contract for shared-mode routing and worktree-per-hostname
- [Mise Tasks](development/mise-tasks.md) — full task reference
- [Deployment](deployment.md) — production Docker setup
