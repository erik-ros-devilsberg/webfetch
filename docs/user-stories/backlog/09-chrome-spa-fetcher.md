---
story: Headless Chrome fetcher for SPAs
created: 2026-06-10
---

## Description

JavaScript-rendered pages return empty shells to the static fetcher. Add a
`ChromeFetcher` implementing the same `Fetcher` interface via headless
Chrome (CDP, `chrome-php/chrome`) so SPAs produce the same readable-content
JSON (roadmap Phase 4).

## Acceptance Criteria

- `ChromeFetcher` implements the `Fetcher` interface — extraction and JSON
  output code are untouched by this story
- Wait-for-render strategy is configurable (network idle and/or fixed
  delay); navigation timeout enforced
- Chrome binary path/availability is configuration; a clear typed failure
  is returned when Chrome is missing
- `chrome-php/chrome` is a `suggest` (or optional) dependency — installing
  webfetch without it must keep the static fetcher fully working
- README documents the SPA setup and a static-first-with-fallback usage
  pattern
- Tests that need Chrome are skipped (not failed) when Chrome is absent;
  CI story documented either way
- `composer check` passes
