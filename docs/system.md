
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

## Repository layout

- `spike/` — throwaway Phase 0 scripts (`fetch.php`, `extract.php`);
  corpus and vendor are gitignored, reproducible via the scripts. Never
  merged into `src/`.
- `src/` — does not exist yet; first production code arrives with story 02
  (scaffolding) and 03 (Fetcher interface).
