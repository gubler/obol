# Git Hooks

Obol uses two hook systems: [Captain Hook](https://github.com/captainhook-git/captainhook) for most hooks, and a standalone script for `pre-merge-commit`.

## Hook Summary

| Hook | Trigger | What Runs |
|------|---------|-----------|
| `pre-commit` | Commit to `main` | **BLOCKED** — use a feature branch |
| `pre-commit` | Commit to any branch | `lint:php`, `cs` (auto-fix), `cs:twig` (auto-fix) |
| `pre-push` | Push any branch | `lint:php`, `cs:check`, `cs:twig:check` |
| `pre-push` | Push to `main` | Above + `sa` (PHPStan) + `test` (full suite) |
| `pre-merge-commit` | Any merge | `lint:php`, `cs`, `cs:twig`, `sa`, `test` |

## Captain Hook

Configured in `captainhook.json` at the repo root. Auto-installed on `composer install` via the `captainhook/plugin-composer` Composer plugin.

### Pre-commit

Two conditional paths:

- **On `main`**: Prints an error and exits. Direct commits to `main` are not allowed.
- **On any other branch**: Runs linters and auto-fixes code style. This means CS Fixer and Twig CS Fixer run in fix mode on every commit, keeping the codebase formatted.

### Pre-push

Runs linters in check-only mode (no auto-fix) on all branches. Additionally, when pushing to `main`, runs PHPStan and the full test suite.

## Standalone Pre-Merge-Commit

Stored in `.githooks/pre-merge-commit` and installed to `.git/hooks/pre-merge-commit` via Composer's `install-hooks` script.

This hook runs the full validation suite on every merge: linters, static analysis, and tests. It ensures that merge commits (especially into `main`) cannot introduce broken code.

## Installation

Hooks are auto-installed on `composer install` via two mechanisms:

1. **Captain Hook** installs its hooks via the Composer plugin
2. **`composer run install-hooks`** copies the standalone `pre-merge-commit` hook

To reinstall manually:

```bash
vendor/bin/captainhook install --force && composer run install-hooks
```

## Important Rules

- **Never use `--no-verify`** to bypass hooks. If a hook fails, fix the underlying issue.
- **Hooks require mise** for running tasks. Ensure `mise` is installed and available in your PATH.
- **Git 2.24+** is required for `pre-merge-commit` hook support.
