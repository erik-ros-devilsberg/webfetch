---
story: v1.0.0 release to Packagist
created: 2026-06-10
---

## Description

First public release of devilsberg/webfetch (roadmap Phase 5). Everything a
stranger needs to install, evaluate, and contribute must be in place.

## Acceptance Criteria

- README is complete: what/why, install (`composer require
  devilsberg/webfetch`), library usage, CLI usage, JSON schema reference or
  link, SPA setup, comparison note vs existing tools
- CHANGELOG.md started; semver policy stated (JSON schema changes are
  breaking)
- CI green on a clean clone; `composer check` passes
- GitHub repository public; issues enabled; tag `v1.0.0` pushed
- Package registered on Packagist with webhook for auto-updates
- phagent's path-repository dependency switched to the Packagist version
- `version.json` and the agile docs reflect the release
