# Git Hooks

Obol's git hooks are plain shell scripts tracked in `.githooks/`. Git is pointed at
that directory with `core.hooksPath`, so the scripts run directly from the repo with
nothing copied into `.git/hooks/`.

## Hook Summary

| Hook | Trigger | What Runs |
|------|---------|-----------|
| `pre-commit` | Commit to `main` | **BLOCKED** - use a feature branch |
| `pre-commit` | Commit to any branch | `lint:php`, `cs` (auto-fix), `cs:twig` (auto-fix) |
| `pre-push` | Push (any branch) | `lint:php`, `cs:check`, `cs:twig:check`, `sa`, `test` |
| `pre-merge-commit` | Any merge | `lint:php`, `cs`, `cs:twig`, `sa`, `test` |

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
- **On any other branch**: runs `lint:php` and the CS fixers in fix mode (`cs`, `cs:twig`),
  keeping the codebase formatted on every commit.

### Pre-push

Runs the full check set on every push, regardless of branch: `lint:php`, `cs:check`,
`cs:twig:check`, PHPStan (`sa`), and the full test suite (`test`). This gates pushes on the
same suite CI runs, catching failures before the server round-trip. (`main` is PR-only, so
there is no branch-specific gating - every push gets the full set.)

### Pre-merge-commit

Runs the full validation suite on every merge - linters, static analysis, and tests - so a
merge commit (especially into `main`) cannot introduce broken code.

### Failure aggregation

Every hook runs all of its checks and aggregates the results (`|| status=1`, no `set -e`),
so a single run reports every failure at once rather than stopping at the first.

## Important Rules

- **Never use `--no-verify`** to bypass hooks. If a hook fails, fix the underlying issue.
- **Hooks require mise** for running tasks. Ensure `mise` is installed and on your PATH.
- **Git 2.24+** is required for `pre-merge-commit` hook support.
