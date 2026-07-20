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

### Production (`compose.yaml`)

Three services:

**`php`** — the Obol application (FrankenPHP)

- Ports: `8080:80`, `8443:443`
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

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `APP_ENV` | No | `prod` | Symfony environment |
| `APP_SECRET` | Yes | `change-me-in-production` | Used for CSRF tokens and encryption |
| `DATABASE_URL` | Yes | Composed from `POSTGRES_*` vars | Full database connection string |
| `POSTGRES_USER` | Yes | `app` | PostgreSQL username |
| `POSTGRES_PASSWORD` | Yes | `!ChangeMe!` | PostgreSQL password |
| `POSTGRES_DB` | Yes | `app` | PostgreSQL database name |
| `MAILER_DSN` | Yes (prod) | `null://null` | Outbound mail transport. Set to a real SMTP DSN (e.g. Fastmail app password) in prod and `.env.local`; the `null://null` default silently drops mail. URL-encode reserved characters in the username (`@` becomes `%40`). Verify with `app:mailer:smoke`. |
| `WEBAUTHN_RP_ID` | Yes (prod) | `localhost` | Passkey relying-party id: a registrable-domain suffix of the site's origin, **without** scheme or port (e.g. `obol.dev88.co`). Passkeys bind to this, so it must be set correctly at first launch and stay stable across deploys - changing it invalidates every existing passkey. The committed default (`localhost`) covers local dev only. |
| `WEBAUTHN_ALLOWED_ORIGINS` | Yes (prod) | `https://obol.lolly.localhost` | The exact origin(s) browsers send during a passkey ceremony (scheme + host + port). Must match the deployed site's origin (e.g. `https://obol.dev88.co`). |

:::caution
Change `APP_SECRET` and `POSTGRES_PASSWORD` from their defaults before deploying to production.
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

```bash
# Set environment variables
export APP_SECRET="your-secret-here"
export POSTGRES_PASSWORD="your-db-password"
export POSTGRES_USER="obol"
export POSTGRES_DB="obol"

# Start the stack
docker compose up -d

# Check logs
docker compose logs -f app
```

The app container waits for the database healthcheck to pass before starting. Migrations run automatically, then FrankenPHP begins serving on ports 80 and 443.

## Container Registry

Docker images are built by CI and pushed to the Gitea Container Registry:

```
code.dev88.work/dev88/obol:latest
code.dev88.work/dev88/obol:{short-sha}
```

Each tag is a single **`linux/amd64`** image, built natively on the Hex runner - the sole deploy target (x86_64).

See [CI/CD](ci-cd.md#native-amd64-build) for details on the build pipeline.
