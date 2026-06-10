# Roadmap — devilsberg/webfetch

Open-source PHP library that turns a webpage into readable-content JSON.
Primary consumer: [phagent](../../phagent/) (via a thin `WebFetchTool` adapter
that lives in phagent, not here). The library itself stays standalone and
framework-free.

## Decisions made (2026-06-10)

- **PHP library, not a C extension** — PHP 8.4's `\Dom\HTMLDocument` (lexbor)
  provides fast HTML5 parsing in C already; a Zend extension buys nothing,
  least of all SPA support.
- **Output: readable content** — title, byline, language, published date, main
  content as markdown, links, metadata. Not a full DOM dump.
- **SPA support in scope, but later** — pluggable `Fetcher` interface; static
  HTTP fetcher first, headless Chrome (CDP) adapter in a later phase.
- **Name**: `devilsberg/webfetch`. License: MIT.
- **Conventions mirror phagent**: PHP `^8.4`, Guzzle, PHPUnit 11, PHPStan
  level 8, php-cs-fixer, `composer check` gate.

## Phases

### Phase 0 — Spike (timeboxed)

- Test `fivefilters/readability.php` + `\Dom\HTMLDocument` on ~20 real pages.
- Verify readability.php compatibility with PHP 8.4 / the new DOM API
  (it historically targeted legacy `DOMDocument`).
- Compare extraction quality against a reference (e.g. Claude Code WebFetch).
- **Gate: go/no-go on reusing readability.php vs writing our own extraction.**

### Phase 0.5 — OSS scaffolding

- MIT license, README, CONTRIBUTING.
- composer.json mirroring phagent's tooling; GitHub Actions CI running
  `composer check`.
- Packagist registration at first tagged release.

### Phase 1 — Core fetch + parse

- `Fetcher` interface; static HTTP implementation (Guzzle): redirects,
  charset detection, timeouts, max-size limits.
- Parse to DOM via `\Dom\HTMLDocument`; tolerate broken HTML; reject or
  pass through non-HTML content types sensibly.

### Phase 2 — Readable-content extraction → JSON

- Stable, versioned JSON schema: title, byline, lang, published date,
  main content (markdown), links, metadata.
- Extraction via the Phase 0 winner, with fallback heuristics.
- Error JSON contract: 4xx/5xx, timeouts, empty extraction, paywalls.

### Phase 3 — phagent integration

- `WebFetchTool` implementing `Phagent\Tool\Tool` — lives in the phagent
  repo, depends on `devilsberg/webfetch`, ~30 lines.
- Optional thin CLI here (`url in → JSON out`) for shell/skill use.

### Phase 4 — SPA adapter

- `ChromeFetcher` via `chrome-php/chrome` (CDP); wait-for-render strategies.
- Same JSON output; consumers can fall back to it when static fetch yields
  empty content.

### Phase 5 — Hardening & release

- Caching, rate limiting / robots etiquette, fixture-based test suite.
- Tag v1.0.0, publish to Packagist.

## Quality gates

- **`composer check`** (php-cs-fixer + PHPStan level 8 + PHPUnit) — every
  story, every commit; CI runs it on push and PR.
- **`composer audit`** in CI — a vulnerable dependency fails the build.
- **JSON Schema conformance** — success and error schemas live in the repo
  as machine-readable JSON Schema files; the test suite validates all
  outputs against them. The schema is the public contract; schema changes
  are breaking changes.
- **Scored extraction corpus** (from Phase 5) — real-world fixture pages
  rated usable / degraded / failed with the Phase 0 rubric; gate fails
  below 80% usable or on any regression vs the committed baseline.
  Implemented: `tests/Corpus/CorpusGateTest.php` over `tests/corpus/`
  (redistributable pages only — see `tests/corpus/ATTRIBUTION.md`).
  Baseline expectations are calibrated against a reader-mode reference
  (Firefox Reader Mode; Anthropic WebFetch as a second opinion), never
  against our own current output — a baseline that pins a bug is worse
  than no baseline.
- **Coverage: report, don't gate** — coverage badge on the README; no
  threshold, TDD is enforced by process instead.
- **No live network in the test suite** — all fetches mocked or fixtures.
- **Post-1.0 candidates**: mutation testing (Infection), performance
  budgets.

## Open items

- phagent's composer.json still says `"license": "proprietary"` — fix when
  phagent goes open source.
- "webfetch" is crowded terminology; discovery relies on the package
  description, not the name.
