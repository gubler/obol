# Getting Started

This guide covers setting up Obol for local development.

## Prerequisites

- **Docker** with the Compose plugin. Any compatible engine works; [OrbStack](https://orbstack.dev/) is recommended on macOS for its faster startup and volume mounts.
- **[mise](https://mise.jdx.dev/)** for task shortcuts.
- **[mkcert](https://github.com/FiloSottile/mkcert)** (optional, for browser-trusted local TLS).
- **[MkDocs Material](https://squidfunk.github.io/mkdocs-material/)** (optional, for editing the docs site).

No PHP, Composer, or Postgres install on the host is required — everything runs inside Docker.

## Setup

### 1. Clone and start the stack

```bash
git clone ssh://git@code.dev88.work:222/dev88/obol.git
cd obol
mise run up
```

The app is now at **https://obol.localhost:8443**. Out of the box Caddy uses a self-signed cert, so your browser will warn about trust.

### 2. (Optional) Wire up trusted TLS

For a browser-trusted dev experience, follow [Local Setup § Enable TLS](development/local-setup.md#3-enable-tls-in-caddy-opt-in). In short: generate a mkcert wildcard cert, set `CADDY_EXTRA_CONFIG` in `.env.local`, restart the stack.

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

- [Local Setup](development/local-setup.md) — shared-proxy mode, worktree-per-hostname, troubleshooting
- [Mise Tasks](development/mise-tasks.md) — full task reference
- [Deployment](deployment.md) — production Docker setup
