# CI/CD

Obol uses Gitea Actions for continuous integration and Docker image builds. The workflow is defined in `.gitea/workflows/ci.yml`.

## Triggers

- **All pull requests** — runs the full lint + test pipeline (covers both feature -> `main` and `main` -> `production` PRs)
- **Merge into `production`** — builds and pushes the Docker image

Nobody pushes to `main` or `production` directly - both are PR-only (the pre-commit hook blocks direct commits to `main`, and `production` only advances by a fast-forward `main` -> `production` PR). The build trigger is the `push` event Gitea emits *when such a PR merges*; that is what `on.push.branches: [production]` and the build job's `gitea.event_name == 'push'` key off. So "push to `production`" here always means "a `main` -> `production` release merged."

Merging a feature PR into `main` likewise emits a push on `main`, but the workflow ignores it (`main` isn't in `on.push.branches`) - that PR already ran the full pipeline.

Lint & Test runs on PRs only - it does **not** re-run when `production` is merged. Because `main` -> `production` is fast-forward, the production HEAD is a commit that already passed Lint & Test on its PR, so re-running it before the build would be redundant.

## Job 1: Lint & Test

Runs on `ubuntu-latest` with PHP 8.5 and Xdebug (for coverage).

### Steps

| Step | What it does |
|------|-------------|
| Checkout | Clone the repository |
| Node setup | `actions/setup-node` (Node 24, npm cache) + `npm ci` for the dev-only JS toolchain |
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
| PHPStan (tests) | Static analysis of `tests/` (relaxed profile, `phpstan-tests.neon`) |
| Biome | JS code style + lint (`npm run cs:check`) |
| tsc --checkJs | JS static analysis (`npm run sa`) |
| Vitest | JS unit tests (`npm run test`) |
| Asset build | `importmap:install`, `tailwind:build`, `asset-map:compile` |
| PHPUnit | Run tests with coverage; `bin/coverage-min.php` enforces the 70% threshold |

The JS toolchain steps are gated on `steps.npm.outcome == 'success'` (the `npm ci` step), independent of the PHP `composer install`. See [Frontend](frontend.md#javascript-toolchain-dev-only) for what they cover.

All steps after `composer install` use the `if: always() && steps.install.outcome == 'success'` condition, so they all run even if earlier steps fail (as long as dependencies were installed). This means you see all failures in one run, not one at a time.

## Job 2: Build Docker Image

Runs only on pushes to `production` (a merged `main` -> `production` release). It does not depend on the Lint & Test job - that already passed on the PR that produced the release commit.

### Steps

1. Checkout the code
2. Set up QEMU (`docker/setup-qemu-action`) and Docker Buildx (`docker/setup-buildx-action`)
3. Set a short SHA environment variable (first 7 chars of the commit hash)
4. Login to the Gitea Container Registry at `code.dev88.work`
5. Build and push a multi-arch image (see below) with two tags:
    - `code.dev88.work/dev88/obol:latest`
    - `code.dev88.work/dev88/obol:{short-sha}`

### Multi-arch builds

The build emits a manifest list covering both **`linux/amd64`** and **`linux/arm64`**, so the same tag pulls correctly on either architecture - amd64 for an x86_64 host (Hex), arm64 for a Mac/arm host. `provenance: false` keeps the published manifest to just those two real platforms (no `unknown/unknown` attestation entries in Gitea's package view).

The dev88 Gitea runner is **arm64**, so the amd64 leg is built under **QEMU emulation** (registered via `setup-qemu-action`). That includes compiling the PHP extensions and running the asset pipeline in the prod builder stage for amd64. If the emulated amd64 leg proves too slow or flaky in practice, the fallback is a native amd64 runner - decide from the observed build time on the first multi-arch CI run (tracked in [#123](https://code.dev88.work/dev88/obol/issues/123)).

### Registry Authentication

The build job uses `secrets.REGISTRY_TOKEN` for authenticating with the Gitea Container Registry. This secret must be configured in the repository's Gitea settings.

## Local Equivalents

The CI pipeline matches what you can run locally:

| CI Step | Local Command |
|---------|--------------|
| PHP-CS-Fixer | `mise run cs:check` |
| Twig-CS-Fixer | `mise run cs:twig:check` |
| PHPStan | `mise run sa` |
| PHPStan (tests) | `mise run sa:tests` |
| Biome | `mise run js:cs:check` |
| tsc --checkJs | `mise run js:sa` |
| Vitest | `mise run js:test` |
| PHPUnit with coverage | `mise run coverage` |

Running `mise run coverage` locally before pushing ensures CI will pass.
