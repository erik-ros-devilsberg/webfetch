---
story: Error JSON contract
created: 2026-06-10
---

## Description

Agents consume tool output as text, so failures must be JSON too — never an
exception or an empty string (roadmap Phase 2). Define and implement one
error shape for every failure mode in the pipeline.

## Acceptance Criteria

- Error JSON schema documented alongside the success schema: schema version,
  source URL, error code (machine-readable enum), human-readable message,
  HTTP status where applicable
- Distinct error codes for at least: DNS/connect failure, timeout, too many
  redirects, response too large, HTTP 4xx, HTTP 5xx, non-HTML content,
  parse failure, empty extraction
- The public entry point never throws for fetch/parse/extract failures —
  it returns error JSON; programmer errors (invalid arguments) may still throw
- Success and error outputs are distinguishable by a single discriminator
  field
- Error schema is published as a machine-readable JSON Schema file; error
  outputs in tests are validated against it
- Tests cover each error code
- `composer check` passes
