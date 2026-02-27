# Deployment

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

Two services:

**`app`** — the Obol application

- Ports: `8080:80`, `8443:443`
- Depends on `database` with healthcheck
- Volume: `uploads_data` mounted at `/app/public/uploads`

**`database`** — PostgreSQL 16 Alpine

- Healthcheck via `pg_isready`
- Volume: `database_data` for persistent storage

### Development overrides (`compose.override.yaml`)

- Exposes the database port locally (random port)
- Adds a `mailer` service (Mailpit) for local SMTP testing on ports 1025 (SMTP) and 8025 (web UI)

## Environment Variables

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `APP_ENV` | No | `prod` | Symfony environment |
| `APP_SECRET` | Yes | `change-me-in-production` | Used for CSRF tokens and encryption |
| `DATABASE_URL` | Yes | Composed from `POSTGRES_*` vars | Full database connection string |
| `POSTGRES_USER` | Yes | `app` | PostgreSQL username |
| `POSTGRES_PASSWORD` | Yes | `!ChangeMe!` | PostgreSQL password |
| `POSTGRES_DB` | Yes | `app` | PostgreSQL database name |

!!! warning
    Change `APP_SECRET` and `POSTGRES_PASSWORD` from their defaults before deploying to production.

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

See [CI/CD](ci-cd.md) for details on the build pipeline.
