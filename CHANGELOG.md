# Changelog

All notable changes to this project are documented in this file, following
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
[Semantic Versioning](https://semver.org/).

**Semver policy:** the JSON output schemas (`schema/`) are the public
contract. Any change to them — field added/removed/renamed, error code
renamed, discriminator changed — is a **breaking change** and bumps the
major version, even if no PHP signature changed.

## [Unreleased]

## [1.0.0] - 2026-06-10

First public release.

### Added

- `Webfetch::create()->fetch($url)` — URL in, JSON string out, never throws
  for pipeline failures.
- Readable-content extraction: fivefilters/readability.php primary, with a
  ratio-based fallback (title + meta description + headline links) for
  index/listing pages; markdown via league/html-to-markdown.
- Versioned, schema-validated output contracts:
  `schema/webfetch-success.schema.json` and
  `schema/webfetch-error.schema.json` (11 machine-readable error codes).
- `Fetcher` seam with two implementations: `StaticFetcher` (Guzzle;
  redirects, timeouts, streamed size cap, charset detection) and the
  optional `ChromeFetcher` (headless Chrome via chrome-php/chrome) for
  JavaScript-rendered pages.
- SSRF guard on by default (`blocked_url`), including redirect targets.
- Opt-in decorators: `CachingFetcher` (PSR-16), `RateLimitedFetcher`
  (per-host spacing), `RobotsAwareFetcher` (`robots_disallowed`).
- `bin/webfetch` CLI: JSON on stdout, exit codes 0/1/2.
- Extraction-quality gate: committed redistributable corpus scored against
  `tests/corpus/baseline.json` (≥80% usable, no regressions) on every test
  run.
