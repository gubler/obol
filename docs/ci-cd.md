# CI/CD

Obol uses Gitea Actions for continuous integration and Docker image builds. The workflow is defined in `.gitea/workflows/ci.yml`.

## Triggers

- **All pull requests** — runs the full lint + test pipeline
- **Pushes to `main`** — runs lint + test, then builds and pushes a Docker image

## Job 1: Lint & Test

Runs on `ubuntu-latest` with PHP 8.5 and Xdebug (for coverage).

### Steps

| Step | What it does |
|------|-------------|
| Checkout | Clone the repository |
| PHP setup | Install PHP 8.5 with `intl`, `mbstring`, `pdo_sqlite`, `zip` extensions |
| Composer validate | `composer validate --no-check-publish --strict` |
| Composer install | Install all dependencies |
| PHP-CS-Fixer | Check code style (no auto-fix in CI) |
| Twig-CS-Fixer | Check Twig template style |
| Lint YAML | Validate Symfony YAML configs |
| Lint Twig | Validate Twig template syntax |
| Lint XLIFF | Validate translation files |
| Lint translations | Validate translation contents |
| Lint container | Validate Symfony service definitions |
| Lint Doctrine | Validate entity mapping (`doctrine:schema:validate --skip-sync`) |
| Composer audit | Check for known security vulnerabilities in dependencies |
| PHPStan | Static analysis at level 9 (`--error-format=github` for inline annotations) |
| Asset build | `importmap:install`, `tailwind:build`, `asset-map:compile` |
| Pest | Run tests with coverage, minimum 70% threshold |

All steps after `composer install` use the `if: always() && steps.install.outcome == 'success'` condition, so they all run even if earlier steps fail (as long as dependencies were installed). This means you see all failures in one run, not one at a time.

## Job 2: Build Docker Image

Runs only on pushes to `main`, after the Lint & Test job passes.

### Steps

1. Checkout the code
2. Set a short SHA environment variable (first 7 chars of the commit hash)
3. Login to the Gitea Container Registry at `code.dev88.work`
4. Build and push the Docker image with two tags:
    - `code.dev88.work/dev88/obol:latest`
    - `code.dev88.work/dev88/obol:{short-sha}`

### Registry Authentication

The build job uses `secrets.REGISTRY_TOKEN` for authenticating with the Gitea Container Registry. This secret must be configured in the repository's Gitea settings.

## Local Equivalents

The CI pipeline matches what you can run locally:

| CI Step | Local Command |
|---------|--------------|
| PHP-CS-Fixer | `mise run cs:check` |
| Twig-CS-Fixer | `mise run cs:twig:check` |
| PHPStan | `mise run sa` |
| Pest with coverage | `mise run coverage` |

Running `mise run coverage` locally before pushing ensures CI will pass.
