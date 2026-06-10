
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

## Parsing (since HTML Parsing sprint)

- `Parser` (`src/Parser/`): `parse(FetchSuccess): ParseOutcome` —
  `ParseSuccess` carries a `\Dom\HTMLDocument`; `ParseFailure` carries
  `ParseError` (`empty_body`, `parse_failure`). Same instanceof pattern as
  fetching.
- Body is normalized to UTF-8 before parsing (mb_convert_encoding from the
  fetch-detected charset; unknown labels fall back to treating input as
  UTF-8). `createFromString(..., LIBXML_NOERROR, 'UTF-8')` so a stale meta
  charset can't mislead the parser post-conversion.
- Gotcha learned in tests: `<title>` is RCDATA — an *unclosed* `<title>`
  swallows the rest of the document as text. Spec-compliant (browsers do
  it too); pages like that will extract garbage and that is correct
  behavior.

## Extraction and JSON output (since Readable Content Extraction sprint)

- `Extractor` (`src/Extractor/`): `extract(FetchSuccess): ExtractOutcome`.
  Primary path: fivefilters/readability.php (charThreshold 100,
  fixRelativeURLs against the final URL). Fallback triggers when content is
  null, <30 words, or <10% of body words — it builds title + meta
  description + headline links (anchor text ≥15 chars, resolved absolute,
  deduped, max 100). Both paths share og/meta harvesting. If fallback also
  yields nothing → `empty_extraction` failure (the JS-shell case).
- `excerpt` is deterministic: og:description → meta description → null.
  Never synthesized from content (readability's auto-excerpt is ignored).
- `byline` (since Extraction Quality Fixes sprint): readability's detection
  only, suppressed when it merely echoes site-wide `meta[name=author]` on a
  non-article page (readability.php itself reads that tag — verified in its
  namePattern). Null over site chrome; the consuming agent can read the
  maintainer from content_markdown.
- Markdown via league/html-to-markdown (strip_tags, atx headers, table
  converter enabled); converter exceptions degrade to plain text, never
  propagate. Before conversion, `<pre>`/`<code>` elements are flattened to
  text content so server-side syntax highlighting (`<span>` soup on
  Packagist/GitHub-rendered pages) never reaches the markdown. Note: the
  converter escapes literal underscores in table cells — correct markdown,
  remember it when writing test expectations.
- Quality reference for baselines: reader-mode rendering (Firefox Reader
  Mode; Anthropic WebFetch as second opinion) — never our own current
  output. The fixture `highlighted-code.html` pins all three defects found
  in the 2026-06-10 live comparison.
- `JsonSerializer` (`src/Serializer/`): `success(PageContent, url,
  DateTimeImmutable)` → JSON string. Clock is a parameter — tests inject a
  fixed instant. Discriminator field `ok`; `schema_version` 1.
- Public contract: `schema/webfetch-success.schema.json` (draft-07),
  human docs in `docs/json-schema.md`. Tests validate every success output
  via opis/json-schema (dev dep). Schema changes are breaking.

## Public entry point and error contract (since Error JSON Contract sprint)

- `Webfetch::create(?Fetcher, ?DateTimeImmutable)->fetch(url, ?FetchOptions): string`
  — URL in, JSON out, never throws for pipeline failures. Factory-based so
  internals stay free to change; inject a fetcher for tests/Chrome, inject
  a timestamp for reproducible output.
- `ErrorCode` enum (`src/ErrorCode.php`) unifies the stage enums into 11
  public codes; the stage enums deliberately share string values so
  `ErrorCode::from($stageError->value)` is the whole mapping.
- Extractor passes parse failures through (`empty_body`, `parse_failure`)
  instead of folding them into `empty_extraction`.
- Error contract: `schema/webfetch-error.schema.json`; every code is
  exercised and schema-validated in `tests/WebfetchTest.php`.
  `parse_failure` is near-unreachable with lexbor — reserved, pinned via a
  direct serializer test.

## CLI (since CLI Entry Point sprint)

- `bin/webfetch <url>` → contract JSON on stdout, nothing else; diagnostics
  on stderr. Exit codes: 0 ok, 1 error JSON, 2 usage error. Flags:
  `--timeout`, `--connect-timeout`, `--max-redirects`, `--max-bytes`,
  `--user-agent`, `--help`.
- All logic lives in `Cli\Command` (argv in, exit code out, injected
  streams + fetcher + timestamp) so tests run in-process; `bin/webfetch`
  is a shim. Declared in composer.json `bin`. No console-framework
  dependency — parsing this small doesn't justify one.

## phagent integration (since Phagent WebFetchTool sprint)

- `Phagent\Tool\WebFetchTool` lives in the **phagent repo** (commit
  2b655f0), wrapping this library via a composer path repository pointing
  at `../php-browse`. When this directory is renamed or the package hits
  Packagist (story 11), update phagent's `repositories.webfetch` entry.
- The dependency direction rule held: webfetch has zero knowledge of
  phagent.
- During integration phagent's remote was 5 commits ahead (OpenAI
  provider, PSR-18 client, v0.0.1); the tool commit was rebased on top,
  lock regenerated, full suite green (40 tests).

## SPA fetching (since Chrome SPA Fetcher sprint)

- `ChromeFetcher` implements the same `Fetcher` seam via chrome-php/chrome
  (OPTIONAL dependency: require-dev + suggest; absent at runtime →
  `browser_unavailable` error, never a throw). Options: binary path, wait
  event (`load`/`networkIdle`), extra render delay, profile dir, noSandbox
  (default true — container reality).
- FetchOptions mapping: totalTimeout → navigation + DOM-read timeout;
  userAgent → browser flag; maxBytes checked on the rendered DOM. Rendered
  pages report status 200 (CDP status not reliably observable via the
  high-level API — documented in the class docblock).
- Recommended pattern (README): static fetch first, retry with
  ChromeFetcher only on `empty_extraction`.
- Integration test renders a file:// fixture whose content is
  JS-injected; it skips without a Chrome binary or when a sandboxed Chrome
  can't start. Verified live against snap chromium on this machine
  (2026-06-10): the injected content round-tripped.

## Safety and etiquette (since Hardening — Safety and Etiquette sprint)

- **SSRF guard ON by default** (`UrlGuard`, wired into StaticFetcher and
  ChromeFetcher's initial URL): private/loopback/link-local IPs,
  localhost, and hostnames resolving to such IPs → `blocked_url`. Redirect
  hops are guarded via Guzzle's on_redirect throwing
  `BlockedRedirectException` (implements GuzzleException so the catch
  satisfies PHPStan). Best-effort: A-records only, resolve-then-fetch race
  documented. Opt-out: `FetchOptions(allowPrivateTargets: true)`.
- **Decorators** (all implement `Fetcher`, all opt-in):
  `CachingFetcher` (PSR-16, successes only, key `webfetch_<sha1(url)>`),
  `RateLimitedFetcher` (per-host min interval; clock/sleep closures
  injectable), `RobotsAwareFetcher` (`User-agent: *` Disallow prefixes,
  per-origin in-memory cache, injectable robots loader →
  `robots_disallowed`).
- **Robots default is OFF** by decision: a user-directed single fetch is
  browser-like traffic, not crawling; browsers don't consult robots.txt.
  Crawling-shaped consumers wrap explicitly.

## Extraction quality gate (since Hardening — Corpus Quality Gate sprint)

- `tests/Corpus/CorpusGateTest.php` runs 11 committed pages (7 real,
  redistributable-only — licenses in `tests/corpus/ATTRIBUTION.md` — plus
  4 synthetic fixtures) through Extractor + JsonSerializer on every
  `composer check`.
- `tests/corpus/baseline.json` is the machine form of the spike rubric:
  per page expected strategy, min word count, content fingerprint, title
  fragment. Any miss = named regression; baseline usable-share must stay
  ≥80% (currently 9/11 = 81.8%; honest degraded: wikipedia-nl truncation,
  php-manual fallback).
- Corpus pages are snapshots — refreshing them is a manual, attributed
  act, never silent.
- Noted during this sprint: php-manual extraction shifted from
  readability (spike) to fallback (charThreshold 100 config) — baseline
  records reality, rating degraded.

## Release state (since v1.0.0 Release Prep sprint)

- `Webfetch::VERSION` is `1.0.0`; CHANGELOG.md (Keep a Changelog) carries
  the v1.0.0 section and the semver policy: **JSON schema changes are
  major-version bumps**, regardless of PHP signatures.
- Clean-clone verified: fresh `git clone` + `composer install` +
  `composer check` is green (87 tests; the Chrome integration test skips
  outside $HOME because snap Chromium cannot read /tmp — skip logic
  distinguishes Chrome error pages from genuine render failures).
- **Publishing is manual and pending**: `docs/RELEASING.md` is the
  checklist (directory rename + phagent path update, GitHub repo + push,
  v1.0.0 tag, Packagist + webhook, flip phagent to `^1.0`). Story 11 stays
  in the backlog until those steps are run with Erik's accounts.

## Examples (since Examples Directory sprint)

- `examples/01-basic-fetch.php` (URL → pretty JSON),
  `02-error-handling.php` (branching on `error_code`, never-throws demo),
  `03-spa-fallback.php` (static-first → ChromeFetcher on
  `empty_extraction`), `04-decorators.php` (caching + rate limiting with a
  call counter proving the cache hit).
- `examples/` is in the php-cs-fixer finder and PHPStan paths — examples
  are gated code, not prose (consequence of the README-staleness
  incident). No example fetches a live URL implicitly; all take argv.

## Repository layout

- `src/` — `Webfetch` class is a placeholder (VERSION constant only);
  `src/Fetcher/` holds the fetching seam described above.
- `tests/` — mirrors `src/`; `SmokeTest` covers autoloading.
- `spike/` — throwaway Phase 0 scripts (`fetch.php`, `extract.php`);
  corpus and vendor are gitignored, reproducible via the scripts. Never
  merged into `src/`.
