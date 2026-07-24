---
title: "Mise Tasks"
---

[mise](https://mise.jdx.dev/) provides task runner shortcuts. All tasks are defined in `mise.toml` at the repo root.

Most tasks run **inside the `php` container** via `./bin/dc exec`. The exceptions: `lint:php` (host-side, needs `git`); the `js:*` tasks (host-side Node/npm - the JS toolchain is dev-only and never enters the container; run `npm ci` once after pulling); and the `docs:*` / `docs-user:*` tasks, which build in their own Node container (`docs/compose.yaml`), with only the `docs:deploy` rsync running on the host.

## Stack control

| Task | Description | Underlying Command |
|------|-------------|-------------------|
| `mise run up` | Start the stack (auto-picks solo vs shared mode) | `./bin/dc up -d --wait` |
| `mise run down` | Stop the stack | `./bin/dc down` |
| `mise run dce -- <cmd>` | Run an arbitrary command in the php container | `./bin/dc exec -T php <cmd>` |

See [Local Setup](../getting-started.md) for how solo and shared modes work.

## Code Quality

| Task | Description | Underlying Command |
|------|-------------|-------------------|
| `mise run lint:php` | PHP syntax check on changed files (host-side) | `php -l` on git-diffed `.php` files |
| `mise run sa` | PHPStan static analysis of `src/` (level 9) | `phpstan --memory-limit=4G analyze` |
| `mise run sa:tests` | PHPStan static analysis of `tests/` (relaxed profile) | `phpstan analyze -c phpstan-tests.neon` |
| `mise run cs` | PHP CS Fixer (auto-fix) | `php-cs-fixer fix` |
| `mise run cs:check` | PHP CS Fixer (check only, no changes) | `php-cs-fixer check --diff` |
| `mise run cs:twig` | Twig CS Fixer (auto-fix) | `twig-cs-fixer fix` |
| `mise run cs:twig:check` | Twig CS Fixer (check only) | `twig-cs-fixer check` |
| `mise run rector` | Rector automated refactoring | `rector` |
| `mise run js:sa` | JS static analysis, `tsc --checkJs` (host-side) | `npm run sa` |
| `mise run js:cs` | JS code style + lint via Biome, auto-fix (host-side) | `npm run cs` |
| `mise run js:cs:check` | JS code style + lint via Biome, check only (host-side) | `npm run cs:check` |
| `mise run check` | Run `sa`, `sa:tests`, `test`, `cs`, `cs:twig`, `js:sa`, `js:test`, `js:cs` in sequence | — |

See [Frontend](../frontend.md#javascript-toolchain-dev-only) for what the JS toolchain covers.

## Testing

| Task | Description | Underlying Command |
|------|-------------|-------------------|
| `mise run test` | All tests | `phpunit` |
| `mise run test:v` | All tests (testdox output) | `phpunit --testdox` |
| `mise run coverage` | Tests with coverage, min 70% | `XDEBUG_MODE=coverage phpunit --coverage-clover` + `bin/coverage-min.php` |
| `mise run coverage:report` | HTML coverage report under `var/coverage/` | `XDEBUG_MODE=coverage phpunit --coverage-html=var/coverage` |
| `mise run infection` | Mutation testing over the Unit suite (on-demand; not in `check`/CI) | `XDEBUG_MODE=coverage infection --threads=4 --test-framework-options=--testsuite=Unit` |
| `mise run igor` | Worker-mode state audit (on-demand + CI-blocking; not in `check`/hooks) | `cache:clear --env=dev` + `igor-php .` |
| `mise run js:test` | JS unit tests via Vitest (host-side) | `npm run test` |

## Assets and Database

| Task | Description | Underlying Command |
|------|-------------|-------------------|
| `mise run tailwind` | Rebuild Tailwind CSS | `php bin/console tailwind:build` |
| `mise run seed` | Load fixtures | `doctrine:fixtures:load --no-interaction` |
| `mise run seed:clear` | Drop schema, re-run migrations (no fixtures) | `doctrine:database:drop` + `create` + `migrate` |

## Documentation

The developer docs (`docs/`) and the end-user guide (`docs-user/`) are Astro Starlight sites built in a Dockerized pnpm container. The `docs-user:*` tasks mirror the `docs:*` set below.

| Task | Description | Underlying Command |
|------|-------------|-------------------|
| `mise run docs:install` | Install docs deps (one-time after clone) | `pnpm install --frozen-lockfile` (in container) |
| `mise run docs:dev` | Live preview at `http://localhost:4321/obol/` | `pnpm dev` (in container) |
| `mise run docs:build` | Build to `docs/dist/` (runs the links validator) | `pnpm build` (in container) |
| `mise run docs:check` | `astro check` (schema + TypeScript) | `pnpm check` (in container) |
| `mise run docs:preview` | Serve the built `docs/dist/` for review | `pnpm preview` (in container) |
| `mise run docs:deploy` | Build and rsync to `hex:/srv/docs/obol/` | `rsync` (host-side) |

## Passing Extra Arguments

Use `--` to pass arguments through to the underlying command:

```bash
# Run a specific test suite
mise run test -- --testsuite=Unit

# Run a single test file
mise run test -- tests/Unit/Entity/SubscriptionTest.php

# Filter tests by name
mise run test -- --filter="subscription"

# PHPStan with extra flags
mise run sa -- --debug
```

## Without mise

If mise is not installed, all commands still work by calling `./bin/dc exec -T php <cmd>` directly:

```bash
./bin/dc up -d --wait
./bin/dc exec -T php vendor/bin/phpunit
./bin/dc exec -T php vendor/bin/phpstan --memory-limit=4G analyze
./bin/dc exec -T php vendor/bin/php-cs-fixer fix
./bin/dc exec -T php vendor/bin/rector
(cd docs && docker compose run --rm docs-builder)   # build the docs site
```
