# ADR-0029: Releases are CalVer versions derived from git tags

- Status: Accepted
- Date: 2026-07-30

## Context

CI published two tags for every production build: `latest`, and the seven-character commit SHA. That
is enough to pin a deploy and enough to roll one back, and it was never enough to answer two
questions that come up the moment there is a host to answer them about.

**Which build is production on?** `OBOL_IMAGE=code.dev88.work/dev88/obol:a1b2c3d` names a build
exactly and tells a reader nothing. Whether it is this week's or March's takes a trip to the registry
or to `git log`.

**Which of these two is newer?** Given `a1b2c3d` and `9f8e7d6`, nothing about the strings answers it.
Ordering releases is the operation a rollback is built on - "go back one" has to be a thing you can
read off the shelf rather than reconstruct.

Both are ordinary operational questions, and both were unanswerable from the one place an operator
actually looks: the deploy env file on the host.

There was a second gap alongside them. A registry tag records that an image exists; it records
nothing about what is in it. "What changed between the build we are running and the one before"
could only be answered by mapping two SHAs back to commits by hand, and only for as long as those
SHAs were still legible to someone.

The scheme this replaces is not hypothetical: Tollo already runs `YYYY.M.PATCH`, from a `VERSION`
file edited by hand on the release PR, with no written procedure. That is the shape to match and the
two weaknesses to not inherit.

## Decision

**A release is a CalVer version, `YYYY.M.PATCH`, derived by CI from the git tags already in the
repository and recorded as a real git tag.**

- **The format is `YYYY.M.PATCH`** - four-digit year, unpadded month, and a patch that counts
  *within the month* from `0`. Several releases in one day simply increment it; it resets on the
  first release of the next month. The patch is not a day marker, so the number stays small and
  says something an operator wants to know: how many releases have already shipped this month.
- **The version is derived, never declared.** `bin/next-version` reads the tags for the current year
  and month, takes the highest patch, and adds one. There is no version file and nothing to edit on a
  release PR.
- **The release is recorded as a git tag, not only a registry tag.** `bin/tag-release` creates an
  annotated `vYYYY.M.PATCH` tag on the release commit and pushes it. The tag is annotated because the
  version is only accurate to the month, so the tag object's timestamp is the only record of when a
  release was actually cut.
- **The git tag is cut before the image is pushed.** See below.
- **The git tag carries a `v` prefix; the image tag does not.** `v2026.7.3` and
  `code.dev88.work/dev88/obol:2026.7.3` are the same release. The prefix keeps
  `git tag --list "v*"` a list of releases and nothing else; the bare form is what `OBOL_IMAGE` pins.
- **A commit is released once.** If HEAD already carries a version tag, that version is reused rather
  than a second one minted, so re-running a build republishes instead of re-numbering.
- **The image is published under three tags**: the version, the short SHA, and `latest`. Production
  pins the version - `compose.prod.yaml` requires `OBOL_IMAGE` rather than defaulting it, so nothing
  in production can float.

### Deriving rather than declaring

A hand-edited version file fails silently. Forgetting the edit breaks no build, fails no check, and
produces a release that looks entirely normal; the symptom is a second image published under a number
that is already taken, and it surfaces when a rollback lands on code nobody expected. The tags cannot
drift from what was actually cut, because cutting the tag *is* the release. Reading them is therefore
the one source that cannot be wrong about its own history.

This also removes the release PR's only manual step, which matters more than it sounds: a `main` ->
`production` PR is fast-forward and otherwise contains nothing to review.

### Cutting the tag before the image is pushed

The two failure modes are not symmetric.

A tag with no image behind it is visible and inert. The next release simply takes the following
number, leaving a gap that means "a build failed here" - and re-running the job fills it, because the
commit's existing version is reused.

An image with no tag behind it is silently wrong. The version never reached git, so the next build
derives the same number again and pushes a *different* image over it. A rollback to that version then
lands on code that was never reviewed under it, and nothing at read time indicates which of the two
is running.

So the harmless failure is chosen deliberately, and the ordering is the mechanism.

### Not SemVer

Obol is deployed, not consumed. There is no downstream that pins a range, no API whose compatibility
a major bump would communicate, and no second consumer to communicate it to. A SemVer number here
would be a compatibility promise with nobody on the other end of it, and deciding whether a release
is a minor or a patch would be pure ceremony on every merge.

What the operator of a deployed application actually wants from a version is recency and order, which
is exactly what a calendar version carries for free.

## Consequences

- The version is a function of the tags, so the tags are load-bearing. Deleting a version tag rewinds
  the counter, and the next release reuses that number for different code. Tags are only deleted to
  abandon a version that was never published.
- A failed build after tagging leaves a gap in the sequence. Documented as expected rather than
  treated as an anomaly.
- The build job needs to write to the repository (`permissions: contents: write`, against a
  workflow-wide read-only default) and needs the full history (`fetch-depth: 0`). A shallow checkout
  fetches no tags, and the failure that produces is quiet: every release would derive `.0`.
- "What changed between two releases" becomes `git log v2026.7.2..v2026.7.3`, which is the return on
  cutting a git tag rather than only a registry tag.
- One version, two spellings. A reader who sees `v2026.7.3` in `git tag` and `2026.7.3` in the deploy
  env file has to know they are the same thing.
- `latest` is still published and nothing in production may pin it. The compose contract check
  enforces that `OBOL_IMAGE` is required; it cannot enforce which tag a host puts there.
- The scripts run once per release, on a merge to `production`, which is the worst place to discover
  a bug in them. `bin/release-check` drives both against throwaway git repositories and a stubbed
  clock, and runs in CI and the pre-commit fast sprint so the behavior is checked per commit. The
  runner's token scope is the one part it cannot cover.

## Alternatives considered

- **SemVer.** Covered above. The compatibility contract it encodes has no audience here.
- **A hand-maintained `VERSION` file** (the Tollo arrangement). Simplest possible mechanism and it
  keeps CI free of git writes. Rejected because its failure mode is a forgotten edit that fails
  nothing at the time and shows up as two images sharing a version - the precise failure the derived
  version cannot have.
- **A date-stamped version, `YYYY.MM.DD` or `YYYY.MM.DD.N`.** Needs no tag lookup at all, since the
  clock supplies the whole number. Rejected because it either cannot express two releases in one day
  or needs a within-day counter anyway, and it makes the common case long and noisy while answering
  no question `YYYY.M.PATCH` leaves open.
- **Deriving the next version from the container registry instead of git tags.** The registry already
  holds every published version, so it is the more direct record of what shipped. Rejected because it
  makes version derivation depend on the registry being reachable and authenticated before a build
  can start, and it would answer only the numbering question - the git tag is what makes the diff
  between two releases readable, so the tag has to exist regardless. Given that, reading it is free.
- **Publishing the version tag but skipping the git tag.** Half the value at less than half the cost.
  Rejected because a registry tag records that an image exists and nothing about what is in it.
- **Tagging after a successful image push.** Avoids the empty-tag gap entirely. Rejected for the
  asymmetry described above: it trades a visible harmless failure for a silent harmful one.
