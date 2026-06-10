---
story: Spike — readable-content extraction with readability.php and \Dom\HTMLDocument
created: 2026-06-10
---

## Description

Before building devilsberg/webfetch, validate that the pure-PHP route works:
PHP 8.4's `\Dom\HTMLDocument` (lexbor) for parsing plus
`fivefilters/readability.php` for readable-content extraction. The outcome
decides whether we reuse readability.php or write our own extraction
(roadmap Phase 0 gate). This is a timeboxed spike — throwaway code in a
scratch directory, findings written down, no production code.

Key unknown: readability.php historically targets legacy `DOMDocument`; its
compatibility with PHP 8.4 and/or the new DOM API is unverified.

## Acceptance Criteria

- A scratch script fetches and extracts ~20 real, diverse pages (news
  articles, docs pages, blog posts, at least 2 JS-heavy pages expected to
  fail, at least 1 non-English page)
- readability.php's PHP 8.4 compatibility is confirmed or refuted, with the
  specific failure modes documented if refuted
- Extraction quality per page is rated against a reference (e.g. Claude
  Code's WebFetch output): usable / degraded / failed
- Findings document in `docs/` covers: quality summary, readability.php vs
  build-our-own recommendation, and whether `\Dom\HTMLDocument` parsing of
  real-world broken HTML held up
- An explicit go/no-go recommendation on reusing readability.php is recorded
- Spike code is not merged into `src/`
