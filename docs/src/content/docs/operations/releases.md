---
title: "Releases and Versioning"
---

Every image Obol deploys carries a version a human can read and order: `2026.7.3` is the fourth
release cut in July 2026. This page covers where that number comes from, what the server pins, and
what to do when a release has to be rolled back or cut again.

The reasoning behind the scheme, and the alternatives weighed, are in
ADR-0029: Releases are CalVer versions derived from git tags.

## The scheme

Versions are CalVer in the form `YYYY.M.PATCH`:

| Component | Meaning |
|-----------|---------|
| `YYYY` | Four-digit year, UTC |
| `M` | Month, unpadded - `7`, not `07` |
| `PATCH` | A counter **within the month**, starting at `0` |

The patch is not a day marker. Three releases on the same afternoon are `.0`, `.1` and `.2`; the
counter resets to `.0` on the first release of the following month. That keeps the number small and
readable - a release's patch tells you how many have already shipped that month.

Given two versions you can always tell which is newer, which is the thing a pair of commit SHAs can
never answer.

### Two spellings of one version

The git tag carries a `v` prefix and the image tag does not:

```
git tag   v2026.7.3
image     code.dev88.work/dev88/obol:2026.7.3
```

They are the same release. The prefix keeps `git tag --list "v*"` a clean list of releases, while
the image tag stays the bare version that `OBOL_IMAGE` pins.

## Where the version comes from

There is no version file to edit. `bin/next-version` derives the version from the tags already in
the repository: it takes the highest patch tagged for the current year and month, adds one, and
prints the result. A month with no tags yet starts at `.0`.

Ask it what the current commit would be released as:

```bash
mise run version
```

Deriving beats declaring because the failure mode of a hand-edited version file is invisible. A
forgotten edit does not fail anything - it ships a second image under a number that is already
taken, and nobody finds out until a rollback lands on the wrong code.

:::note[A commit is released once]
If HEAD already carries a version tag, `bin/next-version` prints *that* version instead of minting a
new one. Rebuilding an already-released commit therefore republishes the same version rather than
advancing the counter, which is what makes re-running a build safe.
:::

## Cutting a release

Releases are cut by merging `main` into `production`; nothing else is required, and nothing is
version-related on a feature PR.

1. Open a `main` -> `production` PR. It runs the full lint and test suite like any other PR.
2. Merge it. `production` only advances by fast-forward, so its HEAD is a commit that already passed.
3. The merge fires the build job, which:
    - derives the next version and pushes the git tag (`bin/tag-release`),
    - builds the production image,
    - pushes it under three tags.

| Image tag | What it is for |
|-----------|----------------|
| `2026.7.3` | The release. This is what a deploy pins. |
| `a1b2c3d` | Short commit SHA - ties the image to the exact source. |
| `latest` | Convenience for ad-hoc pulls. Nothing in production pins it. |

The git tag is created **before** the image is pushed. The ordering is deliberate: a tag with no
image behind it is a visible, harmless gap, while an image published under a version that never
reached git is silently wrong - the next build would derive the same number again and overwrite it,
leaving two different images answering to one tag.

Because the tag is a real git tag rather than only a registry tag, the diff between two releases is
an ordinary git question:

```bash
git log --oneline v2026.7.2..v2026.7.3
```

## What the server pins

The deploy env file (`/etc/obol/deploy.env` by default) names one image:

```env
OBOL_IMAGE=code.dev88.work/dev88/obol:2026.7.3
```

`compose.prod.yaml` requires it - an unset `OBOL_IMAGE` aborts the whole `docker compose` command
rather than defaulting. Pin a version tag, never `latest`: a floating tag means an unrelated restart
can pull something unreviewed, and it makes "which build is production on?" unanswerable from the
host.

Both the `php` and `worker` services run that same image, so there is one line to change and no way
for the two to drift apart.

## Deploying a release

```bash
bin/dc-prod pull
bin/dc-prod up -d
```

See [Deploying Updates](updates.md) for what a recreate discards, what survives it, and how
migrations are applied on the way up.

## Rolling back

A rollback is the same operation as a deploy, pointed at an earlier version:

1. Pick the version to return to - `git tag --list "v*"` lists them, newest patch last within each
   month.
2. Set `OBOL_IMAGE` to that version in the deploy env file.
3. `bin/dc-prod pull && bin/dc-prod up -d`.

Rolling back the image does not roll back the database. An older image boots against a newer schema
on purpose - the entrypoint asks "is anything of mine unapplied", not "does the database match me
exactly" - so the rollback comes up, carrying whatever migrations the releases you stepped back
across applied. Whether that is safe is a question about those migrations: a column added is
harmless to code that ignores it, a column dropped or retyped is not. Check what the range carried
(`git log --oneline v2026.7.1..v2026.7.3 -- migrations/`), and see
[Rolling Back](updates.md#rolling-back) for reverting a migration.

## Re-cutting a release

Re-run the build job for the release commit. `bin/tag-release` sees the version tag already on that
commit, reuses it rather than allocating a new number, pushes the tag if it never reached the remote,
and republishes the image under the same three tags. Nothing needs to be cleaned up first.

:::caution[A failed build leaves its version tag behind]
The tag is cut before the image is built, so a build that fails afterwards leaves a version tag with
no image behind it. Re-running the job is the fix - it reuses that version and publishes the image
that was missing.

If the commit is being abandoned rather than retried, delete the tag on the remote as well as
locally (`git push origin :refs/tags/v2026.7.3`). The counter is derived from the tags, so a tag left
in place is simply skipped and the next release takes the following number.
:::

## Checking the scripts

`bin/next-version` and `bin/tag-release` run exactly once per release, on a production merge, which
is the worst place to discover a bug in them. `bin/release-check` drives both against throwaway git
repositories and a stubbed clock - no runner, no network - and pins the behavior that matters: the
counter increments within a month, resets at a month and year boundary, orders patches numerically
rather than lexically, and never issues one version twice.

```bash
mise run check:release
```

It runs on every commit through the git hooks and in CI, so a broken release script fails long
before a release depends on it.

---

## Changelog

- 2026-07-30 - Document drafted, alongside CI deriving CalVer versions and cutting release tags.
