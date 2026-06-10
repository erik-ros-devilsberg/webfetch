---
sprint: Hardening — Corpus Quality Gate
stories:
  - 10-hardening
status: planned
created: 2026-06-10
---

## Goal

Second half of the hardening story: the scored extraction-quality gate from
the roadmap. A committed corpus of real pages runs end-to-end (fetch
mocked, parse + extract real) on every `composer check`; the suite fails if
under 80% rate usable or any page regresses against the committed baseline.
This is the gate that keeps extraction honest as the code evolves.

## Acceptance Criteria

- [ ] `tests/corpus/` holds committed HTML of redistributable real pages
      only (Wikipedia CC BY-SA, WHATWG CC BY, php.net CC BY, plus existing
      synthetic fixtures); an ATTRIBUTION.md records source URLs, fetch
      date, and licenses — no all-rights-reserved content is committed
- [ ] `tests/corpus/baseline.json` records per page: expected outcome,
      expected extraction strategy, minimum word count, and a content
      fingerprint substring — the machine form of the spike's
      usable/degraded/failed rubric
- [ ] A corpus test runs every page through Extractor + JsonSerializer:
      achieved rating is usable (all expectations met), degraded (success
      but expectations missed), or failed (error outcome)
- [ ] The test FAILS if <80% of corpus pages rate usable, or any page rates
      below its baseline rating; the failure message names the offending
      pages and what was missed
- [ ] All success outputs from the corpus run validate against the success
      schema
- [ ] `docs/roadmap.md`/`docs/json-schema.md` cross-reference the gate;
      README mentions the quality gate in the development section
- [ ] `composer check` and `composer audit` pass

## Tasks

- [ ] Fetch and commit redistributable corpus pages + ATTRIBUTION.md
- [ ] Write baseline.json from a hand-review of current extraction output
- [ ] Write the corpus gate test (data-provider over corpus, scoring, gate
      assertion with named offenders)
- [ ] Wire schema validation into the corpus run
- [ ] Update docs/README references
- [ ] Run `composer check` + `composer audit` until green

## Risks and Open Questions

- Corpus pages are snapshots — they never change, so the gate measures
  code regressions, not web drift; refreshing the corpus is a manual,
  attributed act
- 80% on a small (~10 page) corpus means at most 2 non-usable pages;
  baseline must be honest about today's degraded cases (e.g. infobox noise
  on Wikipedia)
