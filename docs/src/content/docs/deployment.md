---
title: "Deployment"
---

Obol runs in Docker using FrankenPHP, a single binary that combines Caddy (web server) with the PHP runtime. No separate PHP-FPM or Nginx needed.

## Dockerfile

The `Dockerfile` uses a multi-stage build:

### Builder stage (`frankenphp_prod_builder`)

1. Builds on `frankenphp_base`, itself `dunglas/frankenphp:1-php8.5`
2. Installs PHP extensions via `install-php-extensions`: `apcu`, `intl`, `opcache`, `zip`, `pdo_pgsql`
3. Uses production `php.ini`
4. `composer install --no-dev` (production dependencies only)
5. Copies application source
6. Dumps the classmap-authoritative autoloader and compiles `.env` to `.env.local.php`
   via `composer dump-env prod`
7. Compiles frontend assets: `tailwind:build --minify` and `asset-map:compile`
8. Bakes in the end-user help manual: a separate Node stage builds the `docs-user/`
   Astro Starlight site with `DOCS_BASE=/help` and its output is copied to `public/help`,
   so the manual ships in lock-step with the app. Caddy/FrankenPHP serves those files
   statically ahead of PHP, so `/help` needs no Symfony route (ADR-0018).
9. Collects the shared libraries the FrankenPHP and PHP binaries link against, for the
   runtime stage to copy in

### App stage (`frankenphp_prod`)

1. `debian:13-slim` - *not* the FrankenPHP image. Only the binaries, extensions,
   collected libraries and PHP configuration are copied across from the builder
2. CA certificates plus `file`/libmagic, for TLS and Symfony's MIME type detection
3. Copies the built `/app` directory from the builder
4. Drops setuid/setgid bits and runs as `www-data`
5. Declares no `EXPOSE`; the container listens on the port `SERVER_NAME` names (`:80` in
   production, where TLS terminates upstream)
6. Declares no `VOLUME`. `var/` ships with the image and stays on the container's writable layer,
   so recreating the container genuinely gets the new image's compiled cache and built CSS. An
   anonymous volume there would be *reused* on recreate, and the previous release's contents would
   shadow the new image's - an upgrade that silently did not take effect. Everything that has to
   survive a container is in PostgreSQL instead - see [State and storage](#state-and-storage)

:::note
The compiled `.env.local.php` baked in at build time carries the repository's development
defaults, including `WEBAUTHN_RP_ID=localhost`. Real environment variables take precedence over
it, which is why the production overlay must forward every value the application needs - and why
a deploy verifies the resolved values inside the running container rather than trusting the
compose file to look right.
:::

## Docker Compose

There are six compose files, and each name says where in the chain it belongs. None is used alone:
every stack is `compose.yaml` plus the overlays for one environment, and the wrapper scripts pin
which overlays those are so a chain cannot be assembled wrongly by hand.

```mermaid
flowchart TD
    base["compose.yaml<br/><i>services, image tags, DATABASE_URL</i>"]

    base --> dev["bin/dc<br/><i>development</i>"]
    base --> prod["bin/dc-prod<br/><i>production</i>"]

    dev --> devoverlay["+ compose.dev.yaml<br/><i>dev image, bind mounts, Xdebug,<br/>worker hot-reload, Tailwind sidecar</i>"]
    devoverlay --> probe{"lolly network<br/>present?"}
    probe -->|yes| shared["+ compose.dev.shared.yaml<br/><i>joins Lolly, Traefik labels,<br/>SERVER_NAME :80</i>"]
    probe -->|no| solo["+ compose.dev.solo.yaml<br/><i>publishes 127.0.0.1:8080</i>"]

    prod --> prodoverlay["+ compose.prod.yaml<br/><i>OBOL_IMAGE, required secrets,<br/>public origin, database volume</i>"]
    prodoverlay --> tunnel["+ compose.prod.tunnel.yaml<br/><i>cloudflared connector</i>"]

    shared --> devstack(["https://obol.lolly.localhost"])
    solo --> solostack(["http://localhost:8080"])
    tunnel --> prodstack(["https://obol.money"])
```

`bin/dc` picks between solo and shared by probing for the `lolly` Docker network, so there is no flag
to set - start Lolly and the next `bin/dc up -d` joins it. `bin/dc-prod` pins its three files and the
deploy env file outright.

Compose loads none of these on its own. It auto-merges a file named `compose.override.yaml` whenever
no chain is given, and this repository has no such file - so every overlay has to be named to be
applied. That is what makes a production stack unable to inherit development's by omission: there is
nothing to forget to exclude.

:::caution
Because nothing is auto-loaded, a bare `docker compose up -d` in the checkout renders the base stack
without the dev overlay - no bind mounts, no `APP_ENV=dev`, no published ports. Start development
with `bin/dc`, which names the whole chain.

This is the deliberate trade for the guarantee above. Development failing visibly is the cheap half;
production inheriting the dev posture quietly was the expensive one.
:::

The two chains are also disjoint: `compose.dev.yaml` never appears in a production chain, and the
prod and tunnel overlays never appear in a development one. `bin/prod-compose-check` renders the
production chain and asserts both that separation and the absence of the auto-loaded filename, so
neither protection can erode without a check failing.

### Production (`compose.yaml` + `compose.prod.yaml` + `compose.prod.tunnel.yaml`)

The production stack is the base `compose.yaml`, the `compose.prod.yaml` overlay
(deploy image, the required secrets, the public origin, the named database volume)
and the `compose.prod.tunnel.yaml` overlay (the Cloudflare Tunnel connector). The
dev overlays are never part of it - see
[Running in Production](#running-in-production), where `bin/dc-prod` pins the whole
chain so it cannot be assembled wrongly by hand.

The tunnel connector lives in its own overlay because it needs a real tunnel
credential: keeping it separate means the rest of the production stack can still be
started and inspected somewhere no tunnel exists.

Four services:

**`php`** - the Obol application (FrankenPHP)

- Listens on the address `SERVER_NAME` names. In production that is `:80`: TLS terminates at Cloudflare, and the connector reaches the container over plain HTTP on the stack's internal network.
- Publishes no host ports. The connector is the only route in.
- Depends on `database` with healthcheck
- Reports healthy by probing its own `/health` over HTTP, so "healthy" means the application can serve a request rather than that Caddy is up (see [Healthcheck](#healthcheck))
- Mounts nothing. Sessions and the application cache pool are in PostgreSQL, so the container holds nothing worth keeping. Caddy's `/data` and `/config` and the uploads mount are development-only, declared in `compose.dev.yaml`

**`cloudflared`** - the Cloudflare Tunnel connector (`compose.prod.tunnel.yaml`)

- Official `cloudflare/cloudflared` image, pinned to a release tag. The connector ships frequently, and a floating tag could swap during an unrelated restart and break tunnel authentication with nothing on our side having changed.
- Token-managed: the ingress rule (which service to forward to) lives on the tunnel's dashboard page, so the host holds no credentials file to recreate when the stack moves.
- Runs as a compose service rather than on the host, so it can address the app as `php` over the internal network. On the host it would need a published localhost port, defeating the point of having no open ports.
- Waits for `php` to be healthy, so the tunnel is only advertised once the app can answer.

**`worker`** - long-running Messenger consumer

- Same image as `php`, run with `messenger:consume mail async scheduler_default --time-limit=3600`
- Drains three transports in priority order (mail first): `mail` (outbound transactional email), `async` (general off-request work, empty for now), and `scheduler_default` (the Symfony Scheduler)
- Drives every recurring job - hourly payment generation, the daily exchange-rate pull, and the daily cache prune; without it the scheduler never fires, queued mail never sends, and `cache_items` grows without bound
- Depends on `php` being healthy (so vendor install and migrations are already done before it boots), and on `database`
- Sets no `OBOL_RUN_MIGRATIONS`, so it does not migrate - `php` owns that. It still verifies the schema is current and refuses to start if it is not (see [Which container migrates](#which-container-migrates))
- `restart: unless-stopped`; recycles hourly via the time limit
- Present in dev too (base + override), so the scheduler and mail delivery run locally
- In production it carries the full application environment, not just the app secret: async dispatch means the real send *and* the magic-link URL generation happen here rather than in the web request, so it needs the same mail and origin configuration `php` has

**`database`** - PostgreSQL 16 Alpine

- Healthcheck via `pg_isready`
- Storage differs by environment: the base compose uses a checkout-relative bind mount (`./docker/db/data`) for development, and the prod overlay replaces it with the named `database_data` volume so the database is not tied to the deploy checkout's working directory
- In production it is the only mount in the entire stack, which `bin/prod-compose-check` asserts by counting them

:::caution
`docker compose down` does not remove the named volume - the data survives a stop, a restart and a
recreate. `docker compose down --volumes` (`-v`) deletes it outright, and there is no undo. That is
the one command that can destroy the database, which is why `bin/dc-prod` refuses to run any `down`
without an explicit confirmation, and demands a typed phrase when `-v` is present.

Sessions live in this database too, so `-v` signs out every user on top of destroying their data.

Backups do not read this volume. A file-level copy of a running PostgreSQL data directory is not a
valid backup at all - the files are mid-write. Backups are logical dumps taken through the database,
so the storage type underneath is irrelevant to them.
:::

### Development overlay (`compose.dev.yaml`)

- Exposes the database port locally (random port)
- Runs FrankenPHP in worker mode with hot-reload. `FRANKENPHP_WORKER_CONFIG` scopes the worker's file
  watcher to `src`, `config`, `templates`, and `translations` rather than the bare `watch` default
  (`./**/*.{env,php,twig,yaml,yml}` under `/app`). The default also watches `var/cache`, so a Symfony
  dev-cache rebuild - a `cache:clear`, or a lazy rebuild after a config/source edit - rewrites ~2000
  container files at once and storms the watcher into a reload loop that wedges the worker (HTTP stops
  responding). Scoping the watch keeps hot-reload for real edits while breaking that feedback loop; if
  the app ever hangs, `bin/dc restart php` clears a wedged worker.
- Outbound mail uses whatever `MAILER_DSN` you set in `.env.local` (there is no Mailpit catcher). For local/early dev, point it at Fastmail SMTP with an app password; the committed default is `null://null`, a no-op. Verify wiring with `app:mailer:smoke`.
  - URL-encode any reserved characters in the DSN username - notably the `@` in an email login becomes `%40`, so `test@example.com` is written `test%40example.com`: `smtp://test%40example.com:APP_PASSWORD@smtp.fastmail.com:465`.

## Environment Variables

The base `compose.yaml` carries dev-friendly fallbacks (the `Dev default` column) so the local stack
runs with no configuration. The prod overlay deliberately drops those fallbacks for everything the
deploy must get right: it sources them with `${VAR:?...}`, so a missing one aborts `docker compose`
before anything starts rather than booting with a repo-known weak value.

`deploy.env.example` in the repository root is a filled-out template of exactly this table. Copy it to
the host, complete it, and point `COMPOSE_ENV_FILES` at it.

:::note[The application's public identity is `APP_HOST`, not `SERVER_NAME`]
The two are different things that only coincided in development. `SERVER_NAME` is a Caddy directive:
which address to listen on, and whether to fetch a certificate for it. Behind the tunnel, TLS
terminates upstream and Caddy listens on plain HTTP inside a private network, so `SERVER_NAME` is
`:80` - at which point deriving an origin from it would produce `https://:80`. `APP_HOST` is what
users type, what appears in magic-link emails, and what passkeys bind to; `DEFAULT_URI`,
`WEBAUTHN_RP_ID` and `WEBAUTHN_ALLOWED_ORIGINS` are all derived from it.
:::

| Variable | Prod | Dev default | Description |
|----------|------|-------------|-------------|
| `APP_ENV` | Baked `prod` | `dev` | Symfony environment. The prod image bakes `prod`; do not set it in the deploy env. |
| `APP_HOST` | Required (fail-fast) | not used | The public host, without scheme (e.g. `obol.example`). Drives `DEFAULT_URI`, `WEBAUTHN_RP_ID` and `WEBAUTHN_ALLOWED_ORIGINS`. Read by Compose when it renders the stack; nothing in the application reads it, so it is not itself forwarded into the container. |
| `APP_SECRET` | Required (fail-fast) | committed dev value (`.env.dev`) | Signs magic links, remember-me cookies, and email-verification URIs. A known value is a full auth compromise. |
| `OBOL_IMAGE` | Required (fail-fast) | not used (dev builds locally) | The image to run, pinned to a released tag (e.g. `code.dev88.work/dev88/obol:2026.7.1`). Rolling back is editing this one line and running `bin/dc-prod up -d`. |
| `POSTGRES_PASSWORD` | Required (fail-fast) | `!ChangeMe!` | Password for the cluster's bootstrap superuser. Provisions the two roles below and is used by nothing else - not the application, not the migrations. See [Database roles](#database-roles). |
| `OBOL_DB_OWNER_PASSWORD` | Required (fail-fast) | `!ChangeMe!` | Password for the role that owns the schema and runs migrations. Feeds `MIGRATION_DATABASE_URL`. |
| `OBOL_DB_RUNTIME_PASSWORD` | Required (fail-fast) | `!ChangeMe!` | Password for the role the application runs on. Feeds `DATABASE_URL`. |
| `MAILER_DSN` | Required (fail-fast) | `null://null` | Outbound mail transport. URL-encode reserved characters in the username (`@` becomes `%40`). Verify with `app:mailer:smoke`. |
| `MAILER_FROM` | Required (fail-fast) | `Obol <noreply@dev88.co>` | Default sender for transactional mail. Must be an address the transport is authorized to send as. |
| `CLOUDFLARE_TUNNEL_TOKEN` | Required (fail-fast) | not used | Connector credential from the tunnel's dashboard page. Only read by `compose.prod.tunnel.yaml`. |
| `POSTGRES_USER` | Optional | `app` | The bootstrap superuser's name, created by `initdb`. |
| `POSTGRES_DB` | Optional | `app` | PostgreSQL database name. |
| `OBOL_DB_OWNER` | Optional | `obol_owner` | Name of the schema-owning role. |
| `OBOL_DB_RUNTIME` | Optional | `obol_app` | Name of the runtime role. |
| `SERVER_NAME` | Set to `:80` by the overlay | `obol.lolly.localhost` | Caddy's listen address. Not the public host, and not something the deploy env sets. |
| `DEFAULT_URI` | Derived: `https://${APP_HOST}` | per compose mode | Base URI for URLs generated off-request (emails, magic links), where there is no request to derive a host from. |
| `WEBAUTHN_RP_ID` | Derived: `${APP_HOST}` | `localhost` | Passkey relying-party id, *without* scheme or port. Overridable, but should stay unset - see the caution below. |
| `WEBAUTHN_ALLOWED_ORIGINS` | Derived: `https://${APP_HOST}` | `https://obol.lolly.localhost` | The exact origin(s) browsers send during a passkey ceremony (scheme + host + port). |
| `DATABASE_URL_OVERRIDE` | Optional | not used | A complete DSN for the runtime role, replacing the one composed from the parts. Only needed to point at a database outside this stack. |
| `MIGRATION_DATABASE_URL_OVERRIDE` | Optional | not used | The same, for the owner role. Supply both or neither: the privilege split has to survive the move. |

The resulting `DATABASE_URL` carries more than the domain data: sessions and the application cache
pool read it too, the former as its own connection DSN. Pointing it somewhere else moves all three
together.

:::danger
Those guards only work when the interpolation environment is a **deploy-owned env file outside the
checkout**. Compose otherwise falls back to reading the repository's own `.env`, whose committed
development defaults - `MAILER_DSN=null://null`, `WEBAUTHN_RP_ID=localhost`, a host-side
`DATABASE_URL` - would satisfy every `${VAR:?}` above. A deploy missing its real secrets would then
boot silently with a no-op mailer and a `localhost` passkey relying party instead of refusing to
start. `bin/dc-prod` pins `COMPOSE_ENV_FILES` for exactly this reason.

Compose only catches an *unset* var, never one set to a weak value, so still generate strong, unique
secrets.
:::

:::caution
The passkey relying-party id is a *permanent* binding: a credential cannot be rebound after
registration, so if the RP id changes, every passkey registered under the old value stops working.
It is pinned to the registrable apex rather than a host (ADR-0018), so passkeys would survive the
application later moving onto a subdomain. `APP_HOST` is that apex, so leave `WEBAUTHN_RP_ID` unset
and let it derive; set it explicitly only if the application host ever stops being the apex, in which
case it must keep its original value.

Verify the resolved value *inside the running container*, not in the compose file - the image bakes
a compiled `.env.local.php` carrying `WEBAUTHN_RP_ID=localhost`, and this is the one setting that
cannot be corrected after the fact:

```bash
bin/dc-prod exec php php -r 'echo getenv("WEBAUTHN_RP_ID"), "\n";'
```

Magic-link login does not depend on these variables, so a misconfiguration never locks anyone out -
it only disables the passkey fast path.
:::

## Process timezone

The application must run with its process timezone set to UTC. Calendar dates carry the owner's
timezone, applied at read time (see ADR-0021), but instant storage (`createdAt` timestamps) and any ambient-zone date path must not vary with the host's `TZ`. The app pins `date_default_timezone_set('UTC')` at boot (`public/index.php` and `bin/console`) as belt-and-braces; deployments should also set PHP's `date.timezone=UTC` (the FrankenPHP base image already does). The bundled container needs no extra configuration; a custom PHP configuration must not override it to a local zone.

## Entrypoint

`frankenphp/docker-entrypoint.sh` (installed as `/usr/local/bin/docker-entrypoint`) runs before
FrankenPHP starts, for any `frankenphp`, `php` or `bin/console` command:

1. Installs `vendor/` if it is empty. In the prod image it is baked in, so this is a no-op.
2. Waits up to 60 seconds for the database to answer `SELECT 1`, and exits non-zero if it never does.
3. Runs `doctrine:migrations:migrate --no-interaction --all-or-nothing`, but only when
   `OBOL_RUN_MIGRATIONS=1` marks this container the migration owner.
4. Runs `doctrine:migrations:up-to-date` regardless, and exits non-zero if any of its own migrations
   are unapplied.

Migrations therefore always complete before the server accepts a request, which is what makes it safe
for a release to ship a migration and the code depending on it in the same image.

:::danger[A failed migration is fatal]
The entrypoint exits non-zero and FrankenPHP never starts, so the container never serves traffic
against a schema the code does not expect. Under `restart: unless-stopped` it then restart-loops,
which is the visible symptom: a deploy that keeps recreating `php` is a migration that keeps failing,
and `bin/dc-prod logs php` carries the Doctrine error.

There is no flag to soften this. The `sessions` and `cache_items` tables are on the request path, so a
migration failure that leaves them missing means every request touching a session throws. Not
starting is the safe outcome: a container that never comes up is unmistakable, where one that came up
broken has to be noticed.
:::

### Which container migrates

Every container built from this image runs this entrypoint, so migrating had to become something a
container is told to do rather than something it does by default: one service that forgets to opt out
is a second `doctrine:migrations:migrate` racing the first against one database. `php` sets
`OBOL_RUN_MIGRATIONS=1` and is the only container that migrates, which is the right ownership because
it is the container the rest of the stack already gates on. Anything that says nothing - `worker`
today, anything added later - does not migrate.

Step 4 is what makes opting in safe rather than fragile, and it applies to every container including
the owner. Each one asks the database whether its own migrations are applied and refuses to start if
they are not, so:

- Configure nobody to migrate and the whole stack declines to boot. The mistake surfaces as a stack
  that will not come up, not as an application quietly serving against a schema older than its code.
- The worker does not take `php`'s word for it. It gates on `php`'s healthcheck, but it also checks
  the database itself, so a migration that exited zero without doing its job still stops it.

The check deliberately omits `--fail-on-unregistered`. Rolling back to an older image leaves
migrations in the database that its codebase does not carry, and that has to stay bootable; the
question being asked is "is anything of mine unapplied", not "does the database match me exactly".

`bin/prod-compose-check` asserts the wiring: exactly one container opts in, and it is `php`.

## Healthcheck

The image healthcheck requests `GET /health` against the container's own Caddy and fails on anything
that is not a 2xx. That makes "healthy" mean the application can serve a request, which matters
because two other services gate on the signal: the worker waits for `php` before consuming, and the
tunnel connector waits before advertising the tunnel. A signal that means less than they assume sends
visitors to a container that cannot answer them.

### What it covers, and where it stops

Answering at all establishes most of it - Caddy routes, PHP executes, the kernel boots, the container
compiles. On top of that the endpoint makes one round trip to the database, because every real
request touches it: sessions and the application cache pool are tables (see
[State and storage](#state-and-storage)), so a container that cannot reach PostgreSQL throws on
everything a visitor might do while remaining perfectly able to serve a static file.

It deliberately stops there.

- **No schema-version check.** The entrypoint already refuses to boot against an unapplied migration,
  and the schema cannot drift while the process runs, so a per-probe check would re-answer a settled
  question every thirty seconds.
- **No external services.** A probe that fails when a third party is down marks this container
  unhealthy for something restarting it cannot fix. Mail is the case in point: it is dispatched
  through the worker's queue, so a provider outage delays delivery without making the web container
  unable to serve.

The rule of thumb is that the probe should only test things this container can be restarted to fix.

### The endpoint

`GET /health` is public, sits outside `/app` because it is infrastructure rather than application
surface (see ADR-0018),
and returns `200 ok` or `503 unavailable`. Public because the probe runs inside the container with no
session to authenticate; behind the firewall it would answer a redirect to the login page, which
reads as "reachable" to anything that only checks for a response. The body is a bare token for the
same reason it is public - the status code carries the whole answer, and anything more descriptive
would be served to the internet. The reason a check failed goes to the application log instead.

Failure detection is not instant. Docker's defaults probe every 30 seconds and require three
consecutive failures, so a container that breaks reports unhealthy about 90 seconds later. Docker
does not restart an unhealthy container on its own either - the status is a signal for the operator
and for the services that gate on it, not a self-healing mechanism.

## Running in Production

:::danger
A bare `docker compose` command is not safe in production. With no file selection
it renders the base `compose.yaml` alone - no deploy image, no required-secret
guards, no tunnel - so the stack it starts is not the production one. With no env
file pinned it also reads the checkout's `.env`, whose development defaults quietly
satisfy the guards that are meant to abort a deploy missing its real secrets.

Never type `docker compose` on the deploy host. Use `bin/dc-prod`.
:::

`bin/dc-prod` is a thin wrapper that pins both, then hands off to `docker compose`
with whatever arguments you gave it:

- `COMPOSE_FILE=compose.yaml:compose.prod.yaml:compose.prod.tunnel.yaml`
- `COMPOSE_ENV_FILES=/etc/obol/deploy.env` (override the variable to point elsewhere)

Set up the deploy env file once, from the template in the repository root:

```bash
sudo install -m 600 -o "$USER" deploy.env.example /etc/obol/deploy.env
sudo -e /etc/obol/deploy.env
```

Then every deploy operation goes through the wrapper:

```bash
bin/dc-prod pull
bin/dc-prod up -d
bin/dc-prod logs -f php
```

### Making a stray `docker compose` harmless

Nothing can genuinely force a shell to route `docker compose` through the wrapper. What you can do
is make the accidental command *correct* rather than dangerous, by exporting the same two variables
in the deploy user's profile so they are already set for any shell:

```bash
# ~/.profile on the deploy host
export COMPOSE_FILE=compose.yaml:compose.prod.yaml:compose.prod.tunnel.yaml
export COMPOSE_ENV_FILES=/etc/obol/deploy.env
```

With those exported, a bare `docker compose up -d` resolves the same file chain and the same env file
the wrapper would - so it addresses the whole production stack rather than the base alone, and the
required-secret guards still fire. `bin/dc-prod` sets both itself and respects an existing
`COMPOSE_ENV_FILES`, so the two agree rather than fight.

What the environment cannot provide is the teardown guard below, so the wrapper stays the documented
command. If you want the stray invocation to nag as well, a shell function in the same profile does
it:

```bash
docker() {
    if [ "$1" = "compose" ]; then
        echo 'Use bin/dc-prod on this host.' >&2
    fi
    command docker "$@"
}
```

:::caution
Deploy with `pull` then `up -d`, never `down` then `up`. Tearing the stack down stops PostgreSQL along
with everything else, turning a routine deploy into an avoidable outage. `up -d` recreates only the
containers whose configuration or image actually changed.

`bin/dc-prod` enforces this rather than just advising it. Any `down` prompts for confirmation first,
and `down -v` - the one command that deletes the database - demands the phrase `delete the database`
typed in full. Run non-interactively, from a script or an agent, `down` is refused outright unless
`OBOL_CONFIRM_DOWN` is set, so nothing tears the stack down without someone having meant it.
:::

The `php` container waits for the database healthcheck to pass before starting,
runs migrations, and then FrankenPHP begins serving. A migration that fails stops
it there, so nothing downstream comes up: the connector waits for `php` to report
healthy before advertising the tunnel, and the worker waits on the same signal.

### Verifying a deploy

```bash
# The origin and passkey binding as the containers actually resolved them, not as
# the compose file reads. Check the worker too - it mints the magic-link URLs.
bin/dc-prod exec php php -r 'foreach (["DEFAULT_URI","WEBAUTHN_RP_ID","WEBAUTHN_ALLOWED_ORIGINS"] as $k) printf("%-26s %s\n", $k, getenv($k));'
bin/dc-prod exec worker php -r 'echo getenv("DEFAULT_URI"), "\n";'

# Outbound mail actually leaves the host
bin/dc-prod exec php php bin/console app:mailer:smoke you@example.com

# The application container is up rather than restart-looping on a failed migration, and healthy
# rather than merely running - healthy now means it answered its own /health, database included
bin/dc-prod ps php

# The tables the request path depends on exist, and sessions are actually landing in them.
# A signed-in user across the deploy means a non-zero count here.
bin/dc-prod exec php php bin/console dbal:run-sql 'SELECT count(*) FROM sessions'
bin/dc-prod exec php php bin/console dbal:run-sql 'SELECT count(*) FROM cache_items'
```

### Cloudflare-side configuration

Two settings live on the Cloudflare dashboard rather than in this repository, and
the application depends on both:

- The tunnel's **ingress rule**, pointing the public hostname at `http://php:80`.
  Token-managed tunnels keep this dashboard-side, which is what saves the host from
  holding a credentials file.
- A **Transform Rule overwriting `X-Forwarded-For`** with the connecting IP. The
  application trusts private-range proxies and honours that header, so without the
  rule a client could choose the address the application records - which is what
  rate limiting buckets on.

## Container Registry

Docker images are built by CI and pushed to the Gitea Container Registry:

```
code.dev88.work/dev88/obol:2026.7.3
code.dev88.work/dev88/obol:{short-sha}
code.dev88.work/dev88/obol:latest
```

Each tag is a single `linux/amd64` image, built natively on the Hex runner - the sole deploy target (x86_64).

Set `OBOL_IMAGE` in the deploy env to the version tag rather than `latest`, so an unrelated restart
can never pull something unreviewed and a rollback is one edit. The version is CalVer, derived by CI
from the repository's git tags; see [Releases and Versioning](operations/releases.md).

See [CI/CD](ci-cd.md#native-amd64-build) for details on the build pipeline.

## State and storage

The rule the production stack is built on: **anything that must outlive a container lives in
PostgreSQL; everything else is ephemeral and comes from the image.** The reasoning, and the
alternatives weighed, are in
ADR-0026: Deploy-durable state lives in PostgreSQL.

| What | Where | Survives a container recreate |
|---|---|---|
| Domain data | `database` service, `database_data` volume | Yes |
| Sessions | `sessions` table, via `PdoSessionHandler` | Yes |
| Scheduler missed-run state, magic-link replay guard | `cache_items` table, via the application cache pool | Yes |
| Compiled container, asset map, ORM query cache | `var/cache`, the system cache pool | No, and correctly so - the image rebuilds it |
| Compiled assets and built CSS | Baked into the image at build time | No - the point is that the new image's copy wins |
| Application logs | The host's systemd journal, via the container's output | Yes - see [Container logs](#container-logs) |

Both database tables are created by a migration rather than by the adapters' own auto-create, which is
what lets the application run on a role with no DDL rights at all - see
[Database roles](#database-roles). `framework.cache.prefix_seed` is pinned to `obol`: rows now
outlive the container that wrote them, and the namespace Symfony derives by default is a function of
the project directory. Bumping that seed is the one-line way to discard the whole pool, which is what
you want if a cached payload's shape changes across a release.

The session handler opens its *own* PDO connection rather than sharing the ORM's. Its default
transactional locking holds a row lock for the life of the request, and sharing that with the
request's business transaction would make each block the other.

Each table is kept from growing without bound, by a different mechanism. Expired sessions are
collected by `PdoSessionHandler` itself, on roughly one request in a thousand. `cache_items` has no
equivalent - Symfony never calls `prune()` on its own, and the adapter only clears expired rows a
read happens to touch, which the replay guard never is - so a daily job on the application's own
scheduler prunes it. That is why it is the `worker` service, not a host cron entry, that bounds the
table.

:::caution
Uploaded files have no durable home. Uploads are disabled for launch, and the mount backing them is
development-only, so re-enabling them needs object storage or a database column rather than a
volume.
:::

## Database roles

The application connects as a role that **cannot create, alter or drop anything**. A SQL injection or
a compromised PHP process therefore reaches the rows a request could already touch, and not the
schema. The reasoning, and the alternatives weighed, are in
ADR-0030: The application connects as a database role that cannot change the schema.

| Role | What it can do | Who uses it |
|---|---|---|
| `${POSTGRES_USER}` | Superuser. Created by `initdb`. | The provisioning script, break-glass, restore. Nothing else. |
| `obol_owner` | Owns the database and its tables; runs DDL. Not a superuser. | The `migrations` Doctrine connection only. |
| `obol_app` | `SELECT`, `INSERT`, `UPDATE`, `DELETE`. No DDL, no `TRUNCATE`. | Everything else: Doctrine, the session handler, the cache pool. |

Two connection strings follow from that. `DATABASE_URL` names the runtime role and `MIGRATION_DATABASE_URL`
the owner, and both `php` and `worker` carry both - so `doctrine:migrations:migrate` run by hand
picks up the owner role wherever it is invoked, rather than failing with a permission error at the
moment someone is trying to fix something.

`docker/db/init/10-roles.sh` creates the roles and their grants. The postgres image runs it from
`/docker-entrypoint-initdb.d` on the first boot of an empty cluster, so a fresh host reproduces the
whole arrangement with no manual step. It is idempotent - re-running it is how a cluster is converged
and how a rotated password takes effect.

:::note[Future migrations need no grant step]
The script sets default privileges, so a table a later migration creates is usable by the runtime role
the moment it exists. Without them PostgreSQL would grant the runtime role nothing on tables created
after it, and every migration that added one would break the application at runtime - presenting as an
application bug rather than a permissions problem.

Anything Doctrine-backed added later (a Messenger transport, a cache adapter) must set
`auto_setup: false` and get a migration. The runtime role has no DDL rights, so an adapter that tries
to create its own table fails on the request path.
:::

### Restoring a dump

Initialization scripts run only against an empty data directory, so a restore never triggers one. A
restored dump also carries no useful ownership - `pg_restore` assigns everything to whichever role
ran it - so the provisioning script has to be run afterwards:

```bash
# 1. Load the dump as the bootstrap superuser.
bin/dc-prod exec -T database psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" < obol.sql

# 2. Create the roles, take ownership of what was restored, and re-establish the grants.
bin/dc-prod exec -T database sh /docker-entrypoint-initdb.d/10-roles.sh
```

Then check that the application can actually read what was restored, rather than assuming it:

```bash
bin/dc-prod exec php php bin/console dbal:run-sql 'SELECT count(*) FROM subscription'
bin/dc-prod exec php php bin/console doctrine:migrations:up-to-date
```

:::caution
Skipping step 2 does not fail loudly. The application keeps working on the rows it can already read,
and the break surfaces at the next deploy: the owner role cannot read `doctrine_migration_versions` on
restored tables, so the migrator reports the entire history as unapplied and tries to replay it. The
`up-to-date` check above is what catches that while you are still looking.
:::

## Container logs

Production writes no log files. Monolog's handlers stream to the container's stderr, and every
service in the production chain - `php`, `worker`, `database` and `cloudflared` - declares the
`journald` log driver, so that output goes to the host's systemd journal. The journal lives on the
host rather than under the container id, so logs outlive a recreate, and `bin/dc-prod logs` still
reads them.

Container logs are distinct from the application's own log rotation, which production does not use at
all. Development does: it writes rotating files under `var/log`, which persist in the volume the
development image declares at `/app/var`.

The reasoning, and why logs are not shipped to a hosted service yet, is in
ADR-0027: Production logs go to the host journal.

### The bound lives on the host, not in this repository

journald has no per-container size cap, so nothing in the compose files limits how large the journal
grows. Two settings in `/etc/systemd/journald.conf` are what actually keep the disk safe, and both
are host provisioning steps:

| Setting | Why |
|---|---|
| `SystemMaxUse` | Caps total journal size. Left unset it defaults to 10% of the filesystem, which on a disk shared with the database volume is several gigabytes competing with PostgreSQL. |
| `SystemKeepFree` | Reserves free space, so the journal yields disk before the database does. A truncated journal is an inconvenience; a database with no space is an outage. |

:::caution[journald drops messages past its rate limit]
The defaults are `RateLimitIntervalSec=30s` and `RateLimitBurst=10000` per service, and messages past
the burst are discarded rather than queued. `worker` logs every scheduler tick and every message
handled, so it is the service most likely to reach it.

Nothing at read time indicates the record is incomplete, beyond a single "suppressed N messages"
line. Raise or disable the limit when provisioning the host.
:::

Development is not bounded, and is on the daemon's default driver. That is deliberate: the `journald`
driver needs systemd on the *daemon* host, and Docker Desktop and OrbStack both run the daemon inside
a VM that has none, so declaring it in the base compose would stop a development stack from starting
at all.

## HTTP caching

Compiled assets (`/assets`) and the help manual's build output (`/help/_astro`) are served with
`Cache-Control: public, max-age=31536000, immutable`. Both are content-addressed - their filenames
change whenever their contents do - so a cached copy can never be stale.

Everything else keeps `ETag`/`Last-Modified` revalidation. That deliberately includes the help
manual's own pages: their URLs are stable, so an immutable copy in a reader's browser could not be
replaced by any later deploy. Dynamic responses keep the `private, max-age=0, must-revalidate` policy
Symfony sets on them; the cache rule matches only paths that exist as files on disk, which is the
exact inverse of the rule routing requests into PHP.

---

## Changelog

- 2026-08-08 - The application connects as a runtime role with no DDL rights, and migrations as a
  separate owner role. `POSTGRES_PASSWORD` is now the bootstrap superuser's, not the application's;
  `OBOL_DB_OWNER_PASSWORD` and `OBOL_DB_RUNTIME_PASSWORD` are new required secrets. Adds
  [Database roles](#database-roles) and the restore procedure.
- 2026-08-08 - The overlay files are named for their place in the chain: `compose.dev.yaml`,
  `compose.dev.solo.yaml`, `compose.dev.shared.yaml` and `compose.prod.tunnel.yaml`. Nothing is
  auto-loaded any more, so production cannot inherit the dev posture by omission - and a bare
  `docker compose` in the checkout no longer starts a development stack.
- 2026-07-30 - ADRs are named rather than linked to the repository host, so the page does not assume
  where the code is hosted.
- 2026-07-30 - Production logs go to the host's systemd journal: Monolog streams to the container's
  stderr and every production service declares the `journald` driver. Added the Container logs
  section, including the two host settings that bound the journal and the rate limit that drops
  messages past a burst.
- 2026-07-30 - The container healthcheck probes the application's `/health` rather than Caddy's admin
  endpoint, so "healthy" means the application can serve. Added the Healthcheck section.
- 2026-07-30 - A failed migration now stops the container instead of starting it with a warning.
  Migrating is opted into via `OBOL_RUN_MIGRATIONS=1` on `php` alone, and every container verifies the
  schema is current before it starts.
- 2026-07-29 - Sessions and the application cache pool moved into PostgreSQL; the production image
  and stack now declare no volume but the database's. Added the State and storage section and a
  diagram of how the compose files combine.
