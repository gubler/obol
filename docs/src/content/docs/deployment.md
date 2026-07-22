---
title: "Deployment"
---

Obol runs in Docker using FrankenPHP, a single binary that combines Caddy (web server) with the PHP runtime. No separate PHP-FPM or Nginx needed.

## Dockerfile

The `Dockerfile` uses a multi-stage build:

### Builder stage

1. Base image: `dunglas/frankenphp:php8.5-trixie` (Debian 13)
2. Installs system packages: `libpq-dev`, `libicu-dev`, `unzip`
3. Installs PHP extensions: `pdo_pgsql`, `intl`
4. `composer install --no-dev` (production dependencies only)
5. Copies application source
6. Dumps optimized autoloader and compiled `.env`
7. Compiles frontend assets: `importmap:install`, `tailwind:build`, `asset-map:compile`

### App stage

1. Same base image with the same PHP extensions
2. Uses production `php.ini`
3. Copies the entire `/app` directory from the builder
4. Sets up the entrypoint script and upload directories
5. Exposes ports 80 and 443

## Docker Compose

### Production (`compose.yaml` + `compose.prod.yaml`)

The production stack is the base `compose.yaml` plus the `compose.prod.yaml`
overlay (prod image, app secret, `MAILER_DSN`). The dev `compose.override.yaml`
must be excluded - see [Running in Production](#running-in-production) for how
`COMPOSE_FILE` pins this.

Three services:

**`php`** — the Obol application (FrankenPHP)

- Listens on `80`/`443` inside the container; the base stack publishes no host ports (the dev overlays add loopback publishing). Front it with the host's reverse proxy.
- Depends on `database` with healthcheck
- Volume: `uploads_data` mounted at `/app/public/uploads`

**`worker`** — long-running Messenger consumer

- Same image as `php`, run with `messenger:consume mail async scheduler_default --time-limit=3600`
- Drains three transports in priority order (mail first): `mail` (outbound transactional email), `async` (general off-request work, empty for now), and `scheduler_default` (the Symfony Scheduler)
- Drives the hourly payment-generation schedule; **without it the scheduler never fires** and queued mail never sends
- Depends on `php` being healthy (so vendor install and migrations are already done before it boots), and on `database`
- `restart: unless-stopped`; recycles hourly via the time limit
- Present in dev too (base + override), so the scheduler and mail delivery run locally
- In production it carries `MAILER_DSN` (async mail is delivered here, not in the web request)

**`database`** — PostgreSQL 16 Alpine

- Healthcheck via `pg_isready`
- Volume: `database_data` for persistent storage

### Development overrides (`compose.override.yaml`)

- Exposes the database port locally (random port)
- Outbound mail uses whatever `MAILER_DSN` you set in `.env.local` (there is no Mailpit catcher). For local/early dev, point it at Fastmail SMTP with an app password; the committed default is `null://null`, a no-op. Verify wiring with `app:mailer:smoke`.
  - URL-encode any reserved characters in the DSN username - notably the `@` in an email login becomes `%40`, so `test@example.com` is written `test%40example.com`: `smtp://test%40example.com:APP_PASSWORD@smtp.fastmail.com:465`.

## Environment Variables

The base `compose.yaml` carries dev-friendly fallbacks (the `Dev default` column) so the local stack
runs with no configuration. The prod overlay (`compose.prod.yaml`) deliberately drops those fallbacks
for the security-critical secrets: it sources them from the host env with `${VAR:?...}`, so a missing
one **aborts `docker compose` before anything starts** rather than booting with a repo-known weak
value. Set every var marked *fail-fast* in the deploy environment.

| Variable | Prod | Dev default | Description |
|----------|------|-------------|-------------|
| `APP_ENV` | Baked `prod` | `dev` | Symfony environment. The prod image bakes `prod`; do not set it in the deploy env. |
| `APP_SECRET` | **Required (fail-fast)** | committed dev value (`.env.dev`) | Signs magic links, remember-me cookies, and email-verification URIs. A known value is a full auth compromise. |
| `POSTGRES_PASSWORD` | **Required (fail-fast)** | `!ChangeMe!` | PostgreSQL password. Also feeds `DATABASE_URL`, which the base compose composes from the `POSTGRES_*` vars. |
| `POSTGRES_USER` | Optional | `app` | PostgreSQL username. |
| `POSTGRES_DB` | Optional | `app` | PostgreSQL database name. |
| `MAILER_DSN` | Required | `null://null` | Outbound mail transport. Set to a real SMTP DSN (e.g. Fastmail app password); the `null://null` default silently drops mail. URL-encode reserved characters in the username (`@` becomes `%40`). Verify with `app:mailer:smoke`. |
| `SERVER_NAME` | Required | `obol.lolly.localhost` | The deployed host (e.g. `obol.dev88.co`). Drives Caddy's site address and `DEFAULT_URI`. |
| `DEFAULT_URI` | Derived from `SERVER_NAME` | `https://obol.lolly.localhost` | Base URI for URLs generated off-request (emails, magic links). Defaults to `https://${SERVER_NAME}`. |
| `WEBAUTHN_RP_ID` | Required | `localhost` | Passkey relying-party id: a registrable-domain suffix of the site's origin, **without** scheme or port (e.g. `obol.dev88.co`). Passkeys bind to this, so it must be set correctly at first launch and stay stable across deploys - changing it invalidates every existing passkey. |
| `WEBAUTHN_ALLOWED_ORIGINS` | Required | `https://obol.lolly.localhost` | The exact origin(s) browsers send during a passkey ceremony (scheme + host + port). Must match the deployed site's origin (e.g. `https://obol.dev88.co`). |

:::danger
The *fail-fast* secrets (`APP_SECRET`, `POSTGRES_PASSWORD`) have **no prod
fallback**. If any is unset the deploy aborts with an error naming the missing var - by design. Compose
only catches an *unset* var, not one deliberately set to a weak or default value, so still generate
strong, unique secrets.
:::

:::caution
Set `WEBAUTHN_RP_ID` and `WEBAUTHN_ALLOWED_ORIGINS` to the real deployed host before anyone registers a passkey. The RP id in particular is a permanent binding: if it changes later, every passkey registered under the old value stops working and users fall back to magic-link email. Magic-link login does not depend on these variables, so a misconfiguration never locks anyone out - it only disables the passkey fast path.
:::

## Process timezone

The application must run with its process timezone set to **UTC**. Calendar dates carry the owner's
timezone, applied at read time (see [ADR-0021](https://code.dev88.work/dev88/obol/src/branch/main/reference/adr/0021-calendar-date-value-object.md)), but instant storage (`createdAt` timestamps) and any ambient-zone date path must not vary with the host's `TZ`. The app pins `date_default_timezone_set('UTC')` at boot (`public/index.php` and `bin/console`) as belt-and-braces; deployments should also set PHP's `date.timezone=UTC` (the FrankenPHP base image already does). The bundled container needs no extra configuration; a custom PHP configuration must not override it to a local zone.

## Entrypoint

The `docker/entrypoint.sh` script runs before FrankenPHP starts:

```bash
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
exec "$@"
```

Migrations run automatically on every container start. The `--allow-no-migration` flag prevents errors when there are no pending migrations.

## Running in Production

:::danger
A bare `docker compose up -d` is **not** safe in production. With no file
selection, Compose auto-merges `compose.override.yaml`, which forces the dev
posture: `APP_ENV=dev`, the web profiler and debug toolbar, Xdebug, a source
bind-mount, and PostgreSQL published to the host - full debug/info disclosure on
the public internet. Production must run the base plus the prod overlay and must
never load the override.
:::

Pin the file set with `COMPOSE_FILE` so every `docker compose` command in the
deploy resolves to `compose.yaml` + `compose.prod.yaml` and can never auto-load
the override. Set it in the deploy environment alongside the app's own variables,
and make it **persistent** - a sourced env file, the deploy user's shell profile,
or the systemd unit - so it is present for every future `pull`/`up`/`logs`, not
just the current shell.

```bash
# Pin the prod file set - excludes compose.override.yaml
export COMPOSE_FILE=compose.yaml:compose.prod.yaml

# Required secrets - the deploy aborts if any of these is unset (see the table above)
export APP_SECRET="your-secret-here"
export POSTGRES_PASSWORD="your-db-password"

# Other prod environment (see the table above for the full set)
export MAILER_DSN="smtp://user%40example.com:app-password@smtp.example.com:465"
export SERVER_NAME="obol.example.com"
export WEBAUTHN_RP_ID="obol.example.com"
export WEBAUTHN_ALLOWED_ORIGINS="https://obol.example.com"
export POSTGRES_USER="obol"
export POSTGRES_DB="obol"

# Start the stack
docker compose up -d

# Check logs
docker compose logs -f php
```

With `COMPOSE_FILE` exported the plain `docker compose` invocations above are
safe; without it, add `-f compose.yaml -f compose.prod.yaml` to every command
instead. The `php` container waits for the database healthcheck to pass before
starting. Migrations run automatically, then FrankenPHP begins serving.

## Container Registry

Docker images are built by CI and pushed to the Gitea Container Registry:

```
code.dev88.work/dev88/obol:latest
code.dev88.work/dev88/obol:{short-sha}
```

Each tag is a single **`linux/amd64`** image, built natively on the Hex runner - the sole deploy target (x86_64).

See [CI/CD](ci-cd.md#native-amd64-build) for details on the build pipeline.
