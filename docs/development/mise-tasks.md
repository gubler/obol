# Mise Tasks

[mise](https://mise.jdx.dev/) provides task runner shortcuts. All tasks are defined in `mise.toml` at the repo root.

Most tasks run **inside the `php` container** via `./bin/dc exec`. Only `lint:php` and the `docs:*` tasks run on the host (they need `git` and `mkdocs`, respectively).

## Stack control

| Task | Description | Underlying Command |
|------|-------------|-------------------|
| `mise run up` | Start the stack (auto-picks solo vs shared mode) | `./bin/dc up -d --wait` |
| `mise run down` | Stop the stack | `./bin/dc down` |
| `mise run dce -- <cmd>` | Run an arbitrary command in the php container | `./bin/dc exec -T php <cmd>` |

See [Local Setup](local-setup.md) for how solo and shared modes work.

## Code Quality

| Task | Description | Underlying Command |
|------|-------------|-------------------|
| `mise run lint:php` | PHP syntax check on changed files (host-side) | `php -l` on git-diffed `.php` files |
| `mise run sa` | PHPStan static analysis (level 9) | `phpstan --memory-limit=4G analyze` |
| `mise run cs` | PHP CS Fixer (auto-fix) | `php-cs-fixer fix` |
| `mise run cs:check` | PHP CS Fixer (check only, no changes) | `php-cs-fixer check --diff` |
| `mise run cs:twig` | Twig CS Fixer (auto-fix) | `twig-cs-fixer fix` |
| `mise run cs:twig:check` | Twig CS Fixer (check only) | `twig-cs-fixer check` |
| `mise run rector` | Rector automated refactoring | `rector` |
| `mise run check` | Run `sa`, `test`, `cs`, `cs:twig` in sequence | — |

## Testing

| Task | Description | Underlying Command |
|------|-------------|-------------------|
| `mise run test` | All tests (compact output) | `pest --compact` |
| `mise run test:v` | All tests (verbose output) | `pest` |
| `mise run coverage` | Tests with coverage, min 70% | `XDEBUG_MODE=coverage pest --coverage --min=70` |
| `mise run coverage:report` | HTML coverage report under `var/coverage/` | `XDEBUG_MODE=coverage pest --coverage-html=var/coverage` |

## Assets and Database

| Task | Description | Underlying Command |
|------|-------------|-------------------|
| `mise run tailwind` | Rebuild Tailwind CSS | `php bin/console tailwind:build` |
| `mise run seed` | Load fixtures | `doctrine:fixtures:load --no-interaction` |
| `mise run seed:clear` | Drop schema, re-run migrations (no fixtures) | `doctrine:database:drop` + `create` + `migrate` |

## Documentation

| Task | Description | Underlying Command |
|------|-------------|-------------------|
| `mise run docs:serve` | Serve docs locally (live reload, host-side) | `mkdocs serve` |
| `mise run docs:build` | Build docs to `site/` | `scripts/build-docs.sh` |
| `mise run docs:deploy` | Build and deploy to docs.dev88.work | `scripts/deploy-docs.sh` |

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
./bin/dc exec -T php vendor/bin/pest --compact
./bin/dc exec -T php vendor/bin/phpstan --memory-limit=4G analyze
./bin/dc exec -T php vendor/bin/php-cs-fixer fix
./bin/dc exec -T php vendor/bin/rector
mkdocs serve
```
