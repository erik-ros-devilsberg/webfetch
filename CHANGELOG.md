# Changelog

All notable changes to this project are documented in this file, following
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
[Semantic Versioning](https://semver.org/).

**Semver policy:** the JSON output schemas (`schema/`) are the public
contract. Any change to them — field added/removed/renamed, error code
renamed, discriminator changed — is a **breaking change** and bumps the
major version, even if no PHP signature changed.

## [Unreleased]

### Changed

- **Breaking (schema):** `schema_version` is now `2`. Success output gains a
  `source_type` field (currently always `"html"`) so consumers can tell which
  source format the content came from as document formats (e.g. PDF) land.

### Added

- Content-type-dispatched extraction: a pluggable `Extractor` interface with
  `HtmlExtractor` as the `text/html` implementation and a `DispatchingExtractor`
  that selects the extractor by response content type. This is the seam new
  source formats plug into; an unsupported content type returns `not_html`.
- **PDF support** (`application/pdf`): `PdfExtractor` reads text + `title`/
  `byline` metadata into the same JSON contract (`source_type: "pdf"`,
  `extraction_strategy: "pdf"`). Parsing runs in an isolated child process
  with a hard memory cap and wall-clock timeout, so a malicious/malformed PDF
  (decompression bomb, corrupted xref) cannot crash the never-throw facade;
  scanned/image-only PDFs return `empty_extraction`. Adds the
  `smalot/pdfparser` runtime dependency (pure PHP, no system binary;
  LGPL-3.0 — webfetch stays MIT, the copyleft applies only to that
  library's own files). `StaticFetcher` now admits `application/pdf`
  through its content-type gate.

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
