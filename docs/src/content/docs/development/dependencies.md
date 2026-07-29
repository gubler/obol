---
title: "Updating dependencies and recipes"
---

Composer dependency and Symfony recipe updates run from the **host**, not the `php`
container. This is the one place the "everything in-container" workflow is deliberately
inverted, and there is a concrete reason.

## Why host-side

Composer inside the container runs as **root (uid 0)**, while the host user is uid 501, and
the container does not see the host's global gitignore. For a plain `composer update` or
`composer require` that does not matter. But `composer recipes:update` runs
`git update-index --refresh` after it patches files, and:

- root-written files make the host-tracked git index go stale, so the refresh fails
  (`compose.yaml: needs update`) and leaves the recipe half-applied; and
- it refuses to run at all with a dirty git index.

Running from the host avoids both: files are written as uid 501, the index stays
consistent, and the host's gitignore is in effect. The host has PHP 8.5 and Composer, and
its platform matches the container (`composer check-platform-reqs` passes), so the resolved
dependency tree is identical. `vendor/` is inside the `./:/app` bind mount, so host-written
changes are visible to the container immediately.

## Updating Composer dependencies

```bash
composer update --with-all-dependencies    # from the host
```

Run it with `--no-scripts`: the post-update auto-scripts (`cache:clear`, `assets:install`,
`importmap:install`) are container-runtime concerns, and the development image declares `var/` as its
own volume, so the host's copy is not the one the container reads. (The production image declares no
volume - see [State and storage](../deployment.md#state-and-storage).) Clear the container cache
separately if needed:

```bash
./bin/dc exec -T php php bin/console cache:clear
```

After a Mate version bump, reconcile its extension registry and re-check the tool surface:

```bash
mise run mate:discover
mise run mate:tools
```

`composer.json` has `bump-after-update: true`, so it tightens the minimum constraints to the
installed versions automatically. Confirm the lock is publishable-clean with
`composer validate --strict` (the missing `name`/`description` are expected for an app).

## Updating Symfony recipes

List which recipes have updates:

```bash
composer recipes
```

Update them **one at a time, from the host**, reviewing each diff before committing:

```bash
composer recipes:update <vendor/package>
git diff --cached          # review what the recipe changed
# accept, or reject hunks that clobber a deliberate customization
git commit ...
```

`recipes:update` requires a clean git index, so each recipe is committed before the next
can start. Many recipes only advance the lock ref with no file change; commit those as-is.

### Reviewing each recipe

A recipe update is a three-way merge between the old recipe, the new recipe, and our files.
Our config is heavily customized, so most recipe hunks are cosmetic tweaks to stock
scaffolding that do not apply. The discipline is to **accept a change deliberately or reject
it while preserving the customization** - and the recipe ref advances either way, so it stops
reporting "update available" regardless of which hunks you took.

Some recipes patch shared files (`compose.yaml`, `config/packages/*.yaml`) through
`###> vendor/package ###` markers rather than owning them outright. When the three-way merge
cannot reconcile a customized section it leaves conflict markers; resolve them by hand
(usually `git checkout --ours`) and never commit a conflict marker.

Do **not** reach for `composer recipes:install <pkg> --force` to unstick a recipe. It
reinstalls every file the recipe owns, overwriting customizations wholesale (custom Doctrine
types, the database bind-mount, and so on). If you must use it only to advance a stubborn
lock ref, revert every file it touched afterwards and keep only the `symfony.lock` change.
