---
story: PDF Extraction
created: 2026-06-11
---

## Description

Agents using webfetch hit PDFs constantly on the open web (papers, government
documents, reports, datasheets). Today any PDF URL is rejected with a
`not_html` error, which makes webfetch unhelpful for a large slice of real
agent traffic. webfetch needs to read PDFs and return the same readable-content
JSON it produces for HTML.

The fetch is identical for HTML and PDF — only byte-interpretation differs — so
this is an **extraction** concern, not a fetching one. Build the dispatch seam
once and land PDF behind it (Option C): the current HTML extractor becomes the
`text/html` implementation of a shared extractor interface, and a content-type
dispatcher selects the right extractor from `FetchSuccess::$contentType`. DOCX,
ODT and other open document formats are explicitly **out of scope** for this
story but should slot into the same seam later as separate stories.

PDF extraction quality is inherently lower than HTML (no semantic structure,
columns and tables garble, scanned/image-only PDFs yield no text). The output
must be honest about this: scanned/empty PDFs return a typed error, not silent
empty content, and PDF results are scored against the corpus gate at a PDF-
appropriate threshold rather than pretending parity with HTML.

## Acceptance Criteria

- A shared extractor interface exists; the existing HTML extraction is one
  implementation behind it, with no change to HTML output (corpus gate and all
  existing tests stay green).
- A content-type dispatcher selects the extractor based on
  `FetchSuccess::$contentType`; unknown/unsupported types still return the
  existing typed `not_html`-equivalent error.
- `StaticFetcher` (and `ChromeFetcher` as appropriate) accept `application/pdf`
  through to extraction instead of rejecting it at the fetch gate.
- A PDF extractor parses `application/pdf` bytes into `content_markdown`,
  populating `title` and `byline` from PDF metadata when present, empty when
  not (no fabricated bylines — consistent with the honest-byline rule).
- The success JSON gains a `source_type` (or equivalent) field distinguishing
  `html` from `pdf`; `SCHEMA_VERSION` is bumped and the JSON Schema files +
  conformance tests are updated. The schema change is documented as breaking.
- A new `ExtractionStrategy` value records PDF extraction.
- A scanned/image-only or otherwise text-empty PDF returns a typed extraction
  error (e.g. `empty_extraction`), never silent empty content.
- PDF fixtures are added to the corpus and scored with the Phase 0 rubric
  against a reader-mode reference; a PDF-appropriate usable threshold is set and
  gated in CI. Fixtures are redistributable (recorded in ATTRIBUTION.md).
- No live network in the test suite — PDF fixtures are committed bytes.
- Any new dependency is pure-PHP / composer-installable (no required system
  binary), or if a binary is unavoidable the extractor degrades to a typed
  error when it is absent — consistent with the ChromeFetcher pattern.
- `composer check` and `composer audit` pass.
