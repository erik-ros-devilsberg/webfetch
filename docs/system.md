
# System Documentation

This file is maintained by `/agile:wrap-sprint`. Read this to understand the system without reading all the code.

## What this is

devilsberg/webfetch — an open-source PHP library that turns a webpage into
readable-content JSON. Standalone Composer package; phagent consumes it via
an adapter that lives in the phagent repo. See `docs/roadmap.md` for phases
and quality gates.

## Extraction strategy (decided by Phase 0 spike, 2026-06-10)

- **Reuse `fivefilters/readability.php` (^3.3)** for readable-content
  extraction — confirmed fully PHP 8.4 compatible; 83% usable extractions
  on the article/docs corpus subset out of the box. Building our own was
  rejected: it would re-implement Mozilla Readability scoring for parity
  at best.
- **Parse with `\Dom\HTMLDocument`** (PHP 8.4, lexbor) in our own layers —
  parsed all 20 real-world corpus pages (incl. 1.5MB and legacy-HTML pages)
  without errors. readability.php internally uses `masterminds/html5` +
  legacy `DOMDocument`; that stays its internal detail.
- **Known behaviors to design around** (full data: `docs/spike-findings.md`):
  - Near-empty input makes readability.php throw
    `ParseException("Could not parse text.")` → must map to the
    empty-extraction error code (story 06).
  - Main-content bias picks the wrong block on some index/listing pages →
    story 05's fallback heuristic: when extracted words are a tiny fraction
    of body words, fall back to title + meta description + headline links.
  - JS-shell pages (SPAs, consent walls) are unfixable statically →
    Phase 4 ChromeFetcher; static-first-with-fallback pattern.
- The spike's usable/degraded/failed rubric (in `docs/spike-findings.md`)
  becomes the Phase 5 scored corpus gate (≥80% usable, no regressions).

## Tooling and quality gates (since OSS Scaffolding sprint)

- Composer package `devilsberg/webfetch`, MIT, PHP `^8.4`, PSR-4
  `Devilsberg\Webfetch\` → `src/`, `Devilsberg\Webfetch\Tests\` → `tests/`.
- Tooling mirrors phagent exactly: PHPUnit 11 (strict flags, random order),
  PHPStan level 8, php-cs-fixer (@PSR12 + @PHP84Migration +
  declare_strict_types). `composer check` = lint + analyse + test.
- CI: `.github/workflows/ci.yml` runs `composer check` + `composer audit`
  on push/PR. Not yet observed live — no GitHub remote exists; verify when
  one is added (story 11 at the latest).

## Fetching (since Static HTTP Fetcher sprint)

- `Fetcher` interface (`src/Fetcher/`): `fetch(url, ?FetchOptions): FetchOutcome`.
  No HTTP-client types leak through it — that is what lets a ChromeFetcher
  (story 09) drop in.
- `FetchOutcome` is always `FetchSuccess` (finalUrl, status, contentType,
  charset, body) or `FetchFailure` (`FetchError` enum + message + optional
  httpStatus/contentType). Consumers branch with `instanceof`.
- `FetchError` string values (`connection_failed`, `timeout`,
  `too_many_redirects`, `response_too_large`, `http_client_error`,
  `http_server_error`, `not_html`) seed the story 06 error JSON contract —
  renames are breaking.
- `StaticFetcher` (Guzzle): redirects capped via options, timeout vs
  connection failure split on cURL errno 28, body streamed in 8KB chunks so
  the size cap holds against lying Content-Length, charset from header →
  meta scan (first 4KB) → utf-8, non-HTML content types rejected as
  `not_html`. Tests use MockHandler only.

## Repository layout

- `src/` — `Webfetch` class is a placeholder (VERSION constant only);
  `src/Fetcher/` holds the fetching seam described above.
- `tests/` — mirrors `src/`; `SmokeTest` covers autoloading.
- `spike/` — throwaway Phase 0 scripts (`fetch.php`, `extract.php`);
  corpus and vendor are gitignored, reproducible via the scripts. Never
  merged into `src/`.
