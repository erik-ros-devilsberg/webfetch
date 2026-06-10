---
story: Open-source project scaffolding
created: 2026-06-10
---

## Description

Set up devilsberg/webfetch as a publishable open-source Composer package
before feature work starts, mirroring phagent's tooling so both projects
feel the same to contribute to (roadmap Phase 0.5).

## Acceptance Criteria

- `composer.json`: name `devilsberg/webfetch`, MIT license, PHP `^8.4`,
  PSR-4 `Devilsberg\Webfetch\` → `src/`, dev tooling and `test`/`lint`/
  `fix`/`analyse`/`check` scripts mirroring phagent
- `LICENSE` (MIT), `README.md` (what it does, install, usage sketch),
  `CONTRIBUTING.md`
- PHPStan level 8, php-cs-fixer, and PHPUnit 11 configured; `composer check`
  passes on the empty skeleton
- GitHub Actions workflow runs `composer check` and `composer audit` on
  push and pull request; a vulnerable dependency fails the build
- `.gitignore` covers vendor/, caches, and tool artifacts
- Git repository initialized (this directory is not yet a repo)
