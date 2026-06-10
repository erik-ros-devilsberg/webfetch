---
story: CLI entry point
created: 2026-06-10
---

## Description

A thin CLI binary — URL in, JSON out — so the library is usable from shells,
scripts, and agent skills without writing PHP (roadmap Phase 3).

## Acceptance Criteria

- `bin/webfetch <url>` prints the success or error JSON to stdout and exits
  0 on success, non-zero on error
- Flags for the common options: timeout, max size, user-agent
- Registered in composer.json `bin` so it lands in `vendor/bin/webfetch`
- `--help` documents usage; invalid arguments print usage to stderr
- No output other than the JSON document on stdout (diagnostics go to stderr)
- Tested via process-level tests or a testable command class
- `composer check` passes
