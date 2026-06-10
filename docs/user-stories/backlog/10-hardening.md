---
story: Hardening — caching, etiquette, fixtures
created: 2026-06-10
---

## Description

Production-readiness for agent workloads: avoid refetching, behave politely
toward origin servers, and lock behavior in with a fixture-based test suite
(roadmap Phase 5).

## Acceptance Criteria

- Optional response cache behind an interface (PSR-16 compatible), keyed by
  URL, with configurable TTL; disabled by default
- Per-host rate limiting with a configurable minimum interval between
  requests to the same host
- robots.txt handling: configurable respect/ignore, documented default and
  rationale
- Identifying User-Agent by default (package name + version + URL)
- Fixture corpus of saved real-world pages exercised end-to-end (fetch
  mocked, parse + extract real); failures show a diff against expected JSON
- Corpus is a scored quality gate using the spike's rubric: each page rated
  usable / degraded / failed; the suite fails if <80% of pages rate usable
  or if any page's rating regresses against the committed baseline
- SSRF guard: option to refuse private/loopback IP ranges, on by default
- `composer check` passes
