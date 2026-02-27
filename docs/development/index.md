# Development Workflow

Obol follows a structured workflow for all changes: **Issue → Branch → TDD → Commit → PR → Merge → Close**.

## Issues First

Before writing any code, there must be an issue in Gitea defining the work, scope, and what "complete" looks like. If no issue exists, create one first.

## Branching

The `main` branch is protected. No work is done directly on `main`.

Before creating a branch, pull the latest from the parent branch. Branch names reference the issue number and type:

```
feat/42-add-totp-support
fix/15-null-pointer-on-empty-input
refactor/23-extract-auth-module
docs/8-api-reference
```

## Test-Driven Development

For every new feature or bugfix:

1. Write a failing test that validates the desired functionality
2. Run the test to confirm it fails as expected
3. Write only enough code to make the test pass
4. Run the test to confirm success
5. Refactor if needed while keeping tests green

## Commits

Use the [Conventional Commits](https://www.conventionalcommits.org/) framework:

```
feat(auth): add TOTP verification (refs #42)
fix: prevent null pointer on empty input (refs #15)
docs: update API reference (refs #8)
```

Commit frequently. WIP commits on feature branches are fine — they will be squashed on merge.

## Pull Requests

Before creating a PR:

- All tests pass (`mise run test`)
- Static analysis passes (`mise run sa`)
- Code style passes (`mise run cs`)

Use `Closes #42` in the PR description to auto-close the issue on merge.

## Merge Strategy

- **Feature branch → main**: squash merge (clean single commit on main)
- **Feature branch → milestone branch**: squash or retain, depending on complexity
- **Milestone branch → main**: retain commits (each represents a completed feature)

After merge: verify the issue was auto-closed, delete the feature branch.

## Section Contents

- [Standards](standards.md) — code quality rules and conventions
- [Testing](testing.md) — test suites, factories, coverage
- [Git Hooks](git-hooks.md) — automated checks on commit, push, and merge
- [Mise Tasks](mise-tasks.md) — task runner reference
