---
story: phagent WebFetchTool adapter
created: 2026-06-10
---

## Description

Give phagent its webfetch capability: a `WebFetchTool` implementing
`Phagent\Tool\Tool`, wrapping devilsberg/webfetch (roadmap Phase 3).

**Note: the code for this story lives in the phagent repository**
(`../phagent/`), not here — webfetch must never depend on phagent. The
story is tracked here because it gates this project's "works with phagent"
goal.

## Acceptance Criteria

- `WebFetchTool` in phagent implements `name()`, `description()`,
  `inputSchema()` (url required; optional timeout), and `execute()`
  returning the webfetch JSON string verbatim
- phagent's composer.json requires `devilsberg/webfetch` (path repository
  until the Packagist release)
- Tool description tells the model what the JSON contains and that errors
  come back as JSON with an error code
- Registered/registrable in phagent's `ToolRegistry`; an example in
  phagent's `examples/` exercises it
- Tests in phagent mock the library boundary — no live network calls
- phagent's `composer check` passes
