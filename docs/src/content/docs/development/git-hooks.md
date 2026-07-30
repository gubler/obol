---
title: "Git Hooks"
---

Obol's git hooks are plain shell scripts tracked in `.githooks/`. Git is pointed at
that directory with `core.hooksPath`, so the scripts run directly from the repo with
nothing copied into `.git/hooks/`.

## Hook Summary

| Hook | Trigger | What Runs |
|------|---------|-----------|
| `pre-commit` | Commit to `main` | **BLOCKED** - use a feature branch |
| `pre-commit` | Commit to any branch | Fast sprint: `lint:php`, `lint:yaml`, `cs:check`, `cs:twig:check`, `rector:check`, `js:cs:check`, `js:sa`, `check:prod-compose`, `check:entrypoint`, `check:release` |
| `pre-push` | Push (any branch) | Full set: the fast sprint, then `sa`, then `test`, then `js:test` |
| `pre-merge-commit` | Any merge | Full set (identical to `pre-push`) |

The check sprints live in `.githooks/lib.sh` (`fast_checks` and `full_checks`) and are
sourced by all three hooks, so the lists above stay in lockstep. The JS toolchain checks
(`js:cs:check`, `js:sa`, `js:test`) run host-side via npm; see
[Frontend](../frontend.md#javascript-toolchain-dev-only).

## How they are wired

The hooks live in `.githooks/` (`pre-commit`, `pre-push`, `pre-merge-commit`). They are
activated by a single local git setting:

```bash
git config --local core.hooksPath .githooks
```

That setting is applied automatically by the `install-hooks` Composer script, which runs
on every `composer install` and `composer update`:

```jsonc
"install-hooks": "git rev-parse --git-dir >/dev/null 2>&1 && git config --local core.hooksPath .githooks || true",
```

The `git rev-parse` guard makes the script a no-op when there is no usable git
directory (for example during the Docker image build, where `.git` is excluded from the
build context), so `composer install` never fails because of it.

To wire the hooks by hand:

```bash
git config --local core.hooksPath .githooks
```

## Behaviour

### Pre-commit

- **On `main`**: prints an error and exits non-zero. Direct commits to `main` are not allowed.
- **On any other branch**: runs the fast sprint listed above. Style runs in **check mode**
  for CI parity - the hook never rewrites your commit. On a style failure, run `mise run cs`
  (or `cs:twig` / `js:cs`) to fix, then re-stage.

  Three of the fast checks are contract assertions rather than linters. `check:prod-compose`
  renders the deploy compose chain and asserts its shape; it needs the Docker CLI, which the
  CI job does not have, so the hooks are the only place it runs. `check:entrypoint` drives
  the container entrypoint against stub binaries and `check:release` drives the release
  scripts against throwaway git repositories; both need nothing, so CI runs them too.

  What the three have in common is that the code they cover runs somewhere the feedback loop
  is terrible - inside a deploy, inside a container start, on a merge to `production`. Running
  them per commit is what moves the failure back to where it is cheap.

### Pre-push

Runs the full check set on every push, regardless of branch: the fast sprint, then PHPStan
(`sa`), then the PHP test suite (`test`), then the JS unit tests (`js:test`). This gates
pushes on the same suite CI runs, catching failures before the server round-trip. (`main`
is PR-only, so there is no branch-specific gating - every push gets the full set.)

### Pre-merge-commit

Runs the same full set as `pre-push` on every merge - linters, static analysis, and both
test suites - so a merge commit (especially into `main`) cannot introduce broken code.

### Failure handling

The **fast sprint** aggregates its checks (`|| status=1`, no `set -e`), so one run surfaces
every fast failure at once rather than stopping at the first. The **full set** is then
fail-fast *across sprints*: there is no point running PHPStan or the test suites once a fast
check has failed, nor the suites once PHPStan has, so each later sprint runs only if the
previous one passed.

## Important Rules

- **Never use `--no-verify`** to bypass hooks. If a hook fails, fix the underlying issue.
- **Hooks require mise** for running tasks. Ensure `mise` is installed and on your PATH.
- **Git 2.24+** is required for `pre-merge-commit` hook support.
