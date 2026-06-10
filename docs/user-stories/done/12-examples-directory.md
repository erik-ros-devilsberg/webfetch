---
story: Examples directory
created: 2026-06-10
---

## Description

Runnable PHP examples showing how to use devilsberg/webfetch as a library,
so a stranger evaluating the package can go from clone to working output in
under a minute without reading source. README snippets exist but are prose;
the recent README staleness showed that unexecuted documentation drifts —
examples must be real code under the project's quality gates.

Scope is this package only: the phagent integration example lives in the
phagent repo.

## Acceptance Criteria

- `examples/` contains small, single-purpose, runnable scripts:
  - basic fetch: URL in, pretty-printed JSON out
  - error handling: branching on `ok` / `error_code`
  - SPA fallback: static first, ChromeFetcher on `empty_extraction`
    (degrades gracefully when chrome-php is absent)
  - decorator composition: caching + rate limiting around a fetcher
- Each example runs against a committed fixture or a URL passed via argv —
  no hardcoded live URLs fetched implicitly
- `examples/` is covered by php-cs-fixer and PHPStan (added to their
  paths/finders) so examples cannot silently rot
- README links to `examples/` from the Usage section
- `composer check` and `composer audit` pass
