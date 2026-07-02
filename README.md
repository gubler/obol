# Obol

A subscription management application for tracking recurring payments, payment history, and subscription lifecycle events.

Built with Symfony 8.1 and PHP 8.5+.

## Features

- Track subscriptions with cost, payment period, and metadata
- Record and verify individual payments
- Organize subscriptions by category
- Archive/unarchive subscriptions without deletion
- Full audit trail via event sourcing (updates, cost changes, archive/unarchive)
- CQRS via Symfony Messenger (separate query and command buses)

## Requirements

- Docker with the Compose plugin (OrbStack recommended on macOS)
- [mise](https://mise.jdx.dev/) for task shortcuts
- [Lolly](https://code.dev88.work/dev88/lolly) — the shared local dev proxy. Optional but recommended; gives you `https://obol.lolly.localhost` with browser-trusted TLS.

The app runs inside Docker via FrankenPHP — no PHP, Composer, or Postgres install required on the host.

## Setup

```bash
# Bring up the stack
mise run up
```

- **With Lolly running**: app served at **https://obol.lolly.localhost** with browser-trusted TLS.
- **Without Lolly**: app served at **http://127.0.0.1:8080** (plain HTTP, for quick "is it alive" checks).

`bin/dc` auto-detects which mode to use — no flag. See [Lolly's runbook](https://code.dev88.work/dev88/lolly/src/branch/main/docs/agents/integrate-an-app.md) for the full contract.

## Development

All tooling runs inside the `php` container via `./bin/dc exec`. Tasks are defined in `mise.toml`.

```bash
mise run test              # run tests (PHPUnit)
mise run sa                # PHPStan static analysis (level 9)
mise run cs                # PHP CS Fixer (fix)
mise run cs:twig           # Twig CS Fixer (fix)
mise run coverage          # tests + coverage, min 70%
mise run rector            # Rector
mise run tailwind          # rebuild Tailwind CSS
mise run seed              # load fixtures
mise run dce -- php bin/console <cmd>   # arbitrary Symfony command
```

Full task reference: [Mise Tasks](docs/src/content/docs/development/mise-tasks.md).

## Architecture

The domain model centers on four entities, all using ULID primary keys:

- **Subscription** — core entity with immutable properties (`public private(set)`)
- **Payment** — individual payment records (verified or generated)
- **Category** — groups subscriptions
- **SubscriptionEvent** — audit log for all subscription state changes

State changes on `Subscription` go through dedicated methods (`update()`, `archive()`, `recordPayment()`) which emit `SubscriptionEvent` records automatically.

## Documentation

Developer docs are at [docs.dev88.work/obol](https://docs.dev88.work/obol); the end-user guide is at [docs.dev88.work/obol-user](https://docs.dev88.work/obol-user).

### Working on docs locally

The docs are [Astro Starlight](https://starlight.astro.build/) sites (`docs/` for developer docs, `docs-user/` for the user guide), built in a Dockerized pnpm container — nothing to install on the host:

```bash
mise run docs:install   # one-time after clone
mise run docs:dev       # live preview at http://localhost:4321/obol/
mise run docs:build     # build to docs/dist/
mise run docs:deploy    # build and rsync to docs.dev88.work/obol
```

The `docs-user:*` tasks (`install` / `dev` / `build` / `deploy`) drive the end-user guide the same way.

## License

Proprietary.
