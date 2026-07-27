---
title: "Worker-Mode Safety (Igor)"
---

Obol runs on FrankenPHP in **worker mode**: the kernel boots once and the same PHP process
handles many requests. Shared services live for the lifetime of the worker, so any state a shared
service accumulates or mutates persists into the next request - a different user. A field written
on one request and read on the next is a data leak; an unbounded array is a memory leak that
eventually crashes the worker.

[Igor](https://github.com/igor-php/igor-php) is a static linter that audits every **shared
service** in the container for this class of defect: state mutated but never reset, `static`
locals, superglobals, `exit()`/`die()`. It ignores `readonly` classes and properties (immutable by
design) and per-request (non-shared) services.

## Running it

Igor is **on-demand and CI-only**, like [Infection](testing.md). It is a blocking gate in CI but
is deliberately **not** part of `mise run check` or the git hooks: each run must first regenerate
the service map with `cache:clear`, which is too slow for the per-commit sprints.

```bash
mise run igor
```

The task clears the dev cache (to refresh the service map, see below) and runs the audit. The Go
binary self-downloads on first run. A clean run reports every shared service as worker-safe; a new
finding exits non-zero and fails CI.

## How the audit stays accurate

The `IgorPhpBundle` (registered for `dev` and `test` in `config/bundles.php`) hooks the Symfony
compiler and writes the exact set of shared services to `var/cache/dev/igor_service_map.json`.
Igor reads that map instead of guessing, so it audits precisely the services the container builds.

The bundle's compiler pass only runs on **`cache:clear`**, not `cache:warmup` - so the map can go
stale after you add or change a service. The `mise run igor` task and the CI step both clear the
dev cache first to avoid auditing a stale map.

## Why entities and value objects are excluded from the container

Igor surfaced a real misconfiguration: `config/services.yaml` registered all of `src/` as services
with no `exclude`, so entities, enums, value objects, form DTOs, and Doctrine's own types/DQL
functions were each registered as a shared service they never are. Those are plain data or
Doctrine-managed classes, not Symfony services, and registering them both pollutes the container
and makes a worker-mode audit flag their by-design mutations (`Subscription::update()`,
`Payment::amend()`).

The fix is the standard Symfony `exclude` block, restored in `config/services.yaml`:

```yaml
App\:
    resource: '../src/'
    exclude:
        - '../src/Entity/'
        - '../src/Enum/'
        - '../src/ValueObject/'
        - '../src/Dto/'
        - '../src/Doctrine/'
```

`src/Message/` is intentionally **not** excluded: its command/query handlers are real services,
intermixed with the (readonly) command/query DTOs.

## The baseline

Findings Igor cannot act on - third-party services in `vendor/` - are recorded in
`igor-baseline.json` so the audit is green while still surfacing any **new** finding. The baseline is
regenerated with:

```bash
mise run dce -- sh -c 'php bin/console cache:clear --env=dev && vendor/bin/igor-php -generate-baseline .'
```

Regenerate it deliberately - for example after a dependency bump changes which vendor services exist
- and review the diff, so a genuinely new leak is never baselined by accident. The baseline is
vendor-only: the one project pattern Igor used to flag, `AbstractBaseController`'s `#[Required]`
setter, is fixed rather than baselined by declaring the injected buses, logger, and translator
`readonly` (Igor ignores readonly properties), so no project code sits in the baseline.

## Suppressing a finding

Prefer, in order:

1. **Fix it** - make the service stateless, or implement `ResetInterface` and reset every mutated
   property.
2. **Exclude a non-service** from the container in `config/services.yaml` (as above) if the class
   is data or framework-managed, not a service.
3. **`safe_namespaces`** in `igor.json` for whole namespaces that are never worker-relevant
   (`Symfony\`, `Doctrine\`, the Igor bundle itself, and `Zenstruck\Foundry\` - its bundle is
   dev/test-only, so its services never exist in a prod worker).
4. **Baseline** a known-safe finding that none of the above fit.

Line- and element-level `// @igor-ignore` comments and the `#[WorkerSafe]` attribute exist too, but
the attribute imports a dev-only class into the annotated file, so it is avoided in shipped code.
