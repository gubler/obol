# Mise Tasks

[mise](https://mise.jdx.dev/) provides task runner shortcuts. All tasks are defined in `mise.toml` at the repo root.

## Task Reference

### Code Quality

| Task | Description | Underlying Command |
|------|-------------|-------------------|
| `mise run lint:php` | PHP syntax check on changed files | `php -l` on git-diffed `.php` files |
| `mise run sa` | PHPStan static analysis (level 9) | `phpstan --memory-limit=4G analyze` |
| `mise run cs` | PHP CS Fixer (auto-fix) | `php-cs-fixer fix` |
| `mise run cs:check` | PHP CS Fixer (check only, no changes) | `php-cs-fixer check --diff` |
| `mise run cs:twig` | Twig CS Fixer (auto-fix) | `twig-cs-fixer fix` |
| `mise run cs:twig:check` | Twig CS Fixer (check only) | `twig-cs-fixer check` |
| `mise run rector` | Rector automated refactoring | `rector` |

### Testing

| Task | Description | Underlying Command |
|------|-------------|-------------------|
| `mise run test` | All tests (compact output) | `pest --compact` |
| `mise run test:v` | All tests (verbose output) | `pest` |
| `mise run coverage` | Tests with coverage, min 70% | `pest --compact --coverage --min=70` |
| `mise run coverage:report` | HTML coverage report | `pest --coverage --coverage-html=var/coverage` |

### Documentation

| Task | Description | Underlying Command |
|------|-------------|-------------------|
| `mise run docs:serve` | Serve docs locally (live reload) | `mkdocs serve` |
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

If mise is not installed, run the underlying commands directly:

```bash
php vendor/bin/pest --compact
php vendor/bin/phpstan --memory-limit=4G analyze
php vendor/bin/php-cs-fixer fix
php vendor/bin/rector
mkdocs serve
```
