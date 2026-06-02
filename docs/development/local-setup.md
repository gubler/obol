# Local Setup

Obol runs entirely in Docker via FrankenPHP. Tooling commands (`mise run sa`, `mise run test`, etc.) execute inside the `php` container. Only documentation tasks (`mise run docs:*`) run on the host.

## Prerequisites

- **Docker** with the Compose plugin. Any compatible engine works — Docker Desktop, [OrbStack](https://orbstack.dev/), Colima, rootless Docker. OrbStack is recommended on macOS for its faster startup and volume mounts.
- **[mise](https://mise.jdx.dev/)** for task shortcuts.
- **[mkcert](https://github.com/FiloSottile/mkcert)** for browser-trusted local TLS certs.
- **[MkDocs Material](https://squidfunk.github.io/mkdocs-material/)** (optional, for editing the docs site).

## One-time host setup

### 1. Install mkcert and trust its root CA

```bash
brew install mkcert nss     # nss covers Firefox / Chromium NSS trust
mkcert -install             # idempotent — safe to re-run
```

### 2. Generate a wildcard cert (recommended: central location)

The wildcard cert (`*.localhost`) is shared across all sibling apps and the optional shared proxy. The recommended home is `~/Projects/dev-proxy/certs/` — one cert, one place, used by every app:

```bash
mkdir -p ~/Projects/dev-proxy/certs
mkcert -cert-file ~/Projects/dev-proxy/certs/wildcard.pem \
       -key-file  ~/Projects/dev-proxy/certs/wildcard.key \
       "*.localhost" "localhost"
```

Then point this repo's `frankenphp/certs/` at the central cert with a symlink (the directory is gitignored except for `.gitkeep`):

```bash
cd obol
rm -rf frankenphp/certs
ln -s ~/Projects/dev-proxy/certs frankenphp/certs
```

Alternatively, generate a per-app cert directly into `frankenphp/certs/` — works the same but duplicates the cert across apps.

### 3. Enable TLS in Caddy (opt-in)

Solo mode reads `CADDY_EXTRA_CONFIG` from the environment. Set it in `.env.local` to wire the cert into Caddy:

```bash
# obol/.env.local
CADDY_EXTRA_CONFIG='https:// { tls /etc/caddy/certs/wildcard.pem /etc/caddy/certs/wildcard.key }'
```

Without this set, Caddy falls back to its own self-signed cert — fine for CI and for quick "does it boot" checks, but the browser will show a warning.

!!! tip
    `*.localhost` resolves to `127.0.0.1` natively on macOS and most Linux distros — no `/etc/hosts` edits needed.

## Running the stack

```bash
mise run up        # starts the stack
mise run down      # stops it
mise run dce -- php bin/console about   # arbitrary command in the container
```

`bin/dc` (the wrapper behind `$DC`) auto-detects whether a shared reverse proxy is running and picks the right compose overlay. Two modes are supported:

### Solo mode (default)

When no shared proxy is running, `mise run up` publishes the container's own Caddy on **loopback ports**:

- `https://obol.localhost:8443` (mkcert-trusted)
- `http://obol.localhost:8080`

This works out of the box — wildcard cert + FrankenPHP's own TLS termination. The port suffix is the only downside.

### Shared mode (optional, recommended for multi-app setups)

If you want clean URLs (`https://obol.localhost`, no port) *and* to run multiple apps and worktrees simultaneously, front Obol with a shared Traefik instance.

**Steps:**

1. Copy `docs/development/dev-proxy-example.yaml` into a standalone directory (suggested: `~/Projects/dev-proxy/`) and follow the commented setup there.
2. The proxy owns host ports 80 and 443 and terminates TLS using the same mkcert wildcard cert.
3. Create the shared network and start the proxy once:
   ```bash
   docker network create dev-proxy
   cd ~/Projects/dev-proxy && docker compose up -d
   ```
4. Re-run `mise run up` in Obol. The wrapper sees the `dev-proxy` network, uses `compose.shared.yaml`, and Traefik auto-discovers the app via Docker labels.

Reach Obol at `https://obol.localhost` — no port suffix, same mkcert cert.

## Worktree-per-hostname

The shared-mode labels are templated from `SERVER_NAME` and `COMPOSE_PROJECT_NAME`. To run a worktree alongside the main stack:

```bash
git worktree add ../obol-feat-42 feat/42-something
cd ../obol-feat-42
cat > .env.local <<EOF
SERVER_NAME=obol-feat-42.localhost
COMPOSE_PROJECT_NAME=obol-feat-42
EOF
mise run up
```

The wildcard cert covers any `*.localhost` hostname, Traefik adds the route from the label, and you get `https://obol-feat-42.localhost` with zero extra config.

## Database

Inside the container, `DATABASE_URL` is overridden via `compose.yaml` to point at the `database` service (PostgreSQL 16). Migrations run automatically on container start via `frankenphp/docker-entrypoint.sh`.

If you ever want to run the app on the host with `symfony serve`, the tracked `.env` still points at SQLite (`var/data_dev.db`); both paths work, but the container-first flow is the supported one.

## Troubleshooting

**`dev-proxy network not found — using solo mode`**
:   Expected when you haven't set up the shared proxy. Either accept solo mode at `:8443` or set up the proxy.

**TLS warning in the browser**
:   Check that `CADDY_EXTRA_CONFIG` is set in `.env.local` (step 3 above) and that `mkcert -install` has run. If the cert was generated before running `-install`, regenerate it.

**Port 443 already in use**
:   Another service on the host is bound to `:443`. Solo mode uses `:8443` precisely to avoid this; shared mode expects the shared proxy to be the only thing on `:443`.

**Traefik isn't seeing the app**
:   `docker network inspect dev-proxy` should list both `traefik` and the Obol `php` container. If not, confirm the proxy was started on the same Docker engine.
