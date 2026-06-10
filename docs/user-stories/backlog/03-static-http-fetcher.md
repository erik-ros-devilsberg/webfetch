---
story: Fetcher interface and static HTTP fetcher
created: 2026-06-10
---

## Description

Define the pluggable `Fetcher` interface and implement the static HTTP
fetcher on Guzzle (roadmap Phase 1). The interface is the seam that later
lets a headless-Chrome fetcher drop in for SPAs, so it must not leak
HTTP-client details.

## Acceptance Criteria

- `Fetcher` interface: takes a URL (plus options), returns a fetch result
  value object (final URL after redirects, status, content type, charset,
  body) or a typed failure
- Guzzle-based implementation handles: redirects (with a hop limit),
  timeouts (connect + total), response size cap, charset detection
  (header, then meta tag fallback), and a configurable User-Agent
- Non-2xx responses and transport errors produce typed failures, not
  exceptions leaking from Guzzle
- Non-HTML content types are detected and reported as such, not parsed
- Unit tests use Guzzle's MockHandler — no live network calls in the suite
- `composer check` passes
