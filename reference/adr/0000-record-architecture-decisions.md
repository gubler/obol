# 0. Record architecture decisions

- Status: accepted

## Context

We make architectural choices that aren't obvious from the code alone. We want the
rationale recorded where it lives with the code, is versioned, and survives the
loss of any one conversation - rather than in chat logs or memory.

## Decision

We keep Architecture Decision Records (ADRs), in the style described by Michael
Nygard, under `reference/adr/`. Each ADR is a numbered Markdown file with a short
title and the sections: Status, Context, Decision, Consequences. Records are
append-only: a decision that changes gets a new ADR that supersedes the old one,
and the old one is marked superseded rather than deleted.

## Consequences

- New significant decisions cost one small file; the reasoning is preserved.
- Reviewers and future contributors can read *why*, not just *what*.
