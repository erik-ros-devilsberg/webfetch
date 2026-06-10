---
story: HTML parsing to DOM
created: 2026-06-10
---

## Description

Turn a fetch result into a queryable DOM using PHP 8.4's
`\Dom\HTMLDocument` (lexbor), tolerating the broken HTML that real pages
ship (roadmap Phase 1). This is the input boundary for extraction.

## Acceptance Criteria

- A parser component takes a fetch result and returns a `\Dom\HTMLDocument`
  (legacy `DOMDocument` is not used anywhere)
- Malformed/broken HTML parses without fatal errors; parser warnings are
  suppressed or collected, never printed
- Charset from the fetch result is respected; documents are normalized to
  UTF-8 before parsing
- Empty bodies and non-HTML input produce typed failures
- Fixture-based tests cover: valid HTML5, legacy/broken markup, wrong
  charset declaration, empty body
- `composer check` passes
