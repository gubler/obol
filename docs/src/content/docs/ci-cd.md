---
title: "CI/CD"
---

Obol uses Gitea Actions for continuous integration and Docker image builds. The workflow is defined in `.gitea/workflows/ci.yml`.

## Triggers

- **All pull requests** — runs the full lint + test pipeline (covers both feature -> `main` and `main` -> `production` PRs)
- **Merge into `production`** — builds and pushes the Docker image

Nobody pushes to `main` or `production` directly - both are PR-only (the pre-commit hook blocks direct commits to `main`, and `production` only advances by a fast-forward `main` -> `production` PR). The build trigger is the `push` event Gitea emits *when such a PR merges*; that is what `on.push.branches: [production]` and the build job's `gitea.event_name == 'push'` key off. So "push to `production`" here always means "a `main` -> `production` release merged."

Merging a feature PR into `main` likewise emits a push on `main`, but the workflow ignores it (`main` isn't in `on.push.branches`) - that PR already ran the full pipeline.

Lint & Test runs on PRs only - it does **not** re-run when `production` is merged. Because `main` -> `production` is fast-forward, the production HEAD is a commit that already passed Lint & Test on its PR, so re-running it before the build would be redundant.

## Job 1: Lint & Test

Runs on the `default` runner label (Asgard, arm64) with PHP 8.5 and Xdebug (for coverage).

### Steps

| Step | What it does |
|------|-------------|
| Checkout | Clone the repository |
| Node setup | `actions/setup-node` (Node 24, npm cache) + `npm ci` for the dev-only JS toolchain |
| PHP setup | Install PHP 8.5 with `intl`, `mbstring`, `zip`, `pdo_pgsql`, `pcov` (the coverage driver) extensions |
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
2. Set up Docker Buildx (`docker/setup-buildx-action`)
3. Set a short SHA environment variable (first 7 chars of the commit hash)
4. Login to the Gitea Container Registry at `code.dev88.work`
5. Build and push the image (see below) with two tags:
    - `code.dev88.work/dev88/obol:latest`
    - `code.dev88.work/dev88/obol:{short-sha}`

### Native amd64 build

The build job runs on the `amd64` runner label (Hex, x86_64) and builds a single **`linux/amd64`** image natively - no QEMU emulation and no multi-arch manifest. Hex is the sole deploy target, so amd64 is the only architecture that needs to ship. `provenance: false` keeps the published manifest free of `unknown/unknown` attestation entries in Gitea's package view.

This replaces the earlier QEMU-emulated multi-arch build (the amd64 leg used to run under emulation on the arm64 runner; tracked in [#123](https://code.dev88.work/dev88/obol/issues/123) and [#259](https://code.dev88.work/dev88/obol/issues/259)). If an arm64 deploy target appears later, restore the second leg as a matrix build on the `arm64` runner and stitch the two with a manifest-merge job.

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
