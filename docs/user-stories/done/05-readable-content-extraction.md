---
story: Readable-content extraction to JSON
created: 2026-06-10
---

## Description

The core feature: extract the readable content from a parsed page and emit
it as a stable, versioned JSON schema (roadmap Phase 2). Extraction approach
(reuse `fivefilters/readability.php` vs build our own) follows the Phase 0
spike's recommendation — this story depends on story 01.

## Acceptance Criteria

- A versioned JSON schema is documented in `docs/` covering: schema version,
  source URL, fetched-at timestamp, title, byline, language, published date,
  main content as markdown, links (text + href), and metadata
  (og:/meta tags worth keeping)
- Extraction implemented per the spike's go/no-go decision, with fallback
  heuristics when the primary extractor returns nothing (e.g. fall back to
  `<title>`, meta description, or body text)
- HTML-to-markdown conversion preserves headings, lists, links, code blocks,
  and emphasis; scripts, styles, nav/footer boilerplate are excluded
- Output is valid UTF-8 JSON; fields that could not be extracted are null,
  never absent
- The schema is published as a machine-readable JSON Schema file in the
  repo; every test output is validated against it in the test suite
- Fixture-based tests cover an article page, a docs page, a minimal page,
  and a page where extraction fails
- `composer check` passes
