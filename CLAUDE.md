# devilsberg/webfetch

Open-source PHP library that turns a webpage into readable-content JSON
(title, byline, markdown content, links, metadata). 

See `docs/roadmap.md` for phases and the decisions behind them.

## Tech & conventions

- PHP `^8.4` — use the new `\Dom\HTMLDocument` (lexbor), not legacy `DOMDocument`
- License: MIT. This is a public open-source contribution — keep code,
  docs, and commit history publishable
- PSR-4: `Devilsberg\Webfetch\` → `src/`, tests in `tests/`
- Tooling mirrors phagent: Guzzle, PHPUnit 11, PHPStan level 8
- **`composer check`** (php-cs-fixer + PHPStan level 8 + PHPUnit) — every
  story, every commit; CI runs it on push and PR.
- **`composer audit`** in CI — a vulnerable dependency fails the build.
- Architecture: pluggable `Fetcher` interface — static HTTP fetcher first,
  headless Chrome (CDP) adapter for SPAs in a later phase
- Output is a stable, versioned JSON schema; errors are JSON too

## Quality gates

- **JSON Schema conformance** — success and error schemas live in the repo
  as machine-readable JSON Schema files; the test suite validates all
  outputs against them. The schema is the public contract; schema changes
  are breaking changes.
- **Scored extraction corpus** (from Phase 5) — real-world fixture pages
  rated usable / degraded / failed with the Phase 0 rubric; gate fails
  below 80% usable or on any regression vs the committed baseline.
  Implemented: `tests/Corpus/CorpusGateTest.php` over `tests/corpus/`
  (redistributable pages only — see `tests/corpus/ATTRIBUTION.md`).
  Baseline expectations are calibrated against a reader-mode reference
  (Firefox Reader Mode; Anthropic WebFetch as a second opinion), never
  against our own current output — a baseline that pins a bug is worse
  than no baseline.
- **Coverage: report, don't gate** — coverage badge on the README; no
  threshold, TDD is enforced by process instead.
- **No live network in the test suite** — all fetches mocked or fixtures.
- **Post-1.0 candidates**: mutation testing (Infection), performance
  budgets.


## Agile Workflow

This project uses the agile plugin. Follow these rules when building features.

### Flow

```
1. Human writes user stories to docs/user-stories/backlog/
2. /agile:shape <story-slug> [<story-slug2> ...]
        → product-manager reads stories and shapes a sprint plan → saved to docs/sprints/
        STOP: human reviews and approves plan
3. /agile:execute docs/sprints/<sprint-slug>.md
        → developer implements (TDD: tests first, then implement)
        STOP: human reviews the work
4. /agile:review (optional, ad-hoc)
        → reviewer reports findings inline
        → human fixes defects now or creates new user stories
5. /agile:wrap-sprint
        → documents sprint in docs/system.md
        → moves user stories to docs/user-stories/done/
        → deletes sprint plan
6. /agile:commit → commit and push
```

### Rules

- Never start building without an approved sprint plan in `docs/sprints/`
- Sprint plans are the single source of truth for the sprint — update them as execution progresses
- Developer writes tests first, then implements — never skip writing tests
- Review is user invoked — trigger it with `/agile:review`
- Defects found in review become new user stories
- Do not make changes outside project directory

### Directory structure

- `docs/user-stories/backlog/` — pending user stories (human-written)
- `docs/user-stories/done/` — completed user stories (moved here by `/agile:wrap-sprint`)
- `docs/sprints/` — active sprint plans (deleted after `/agile:wrap-sprint`)
- `docs/system.md` — cumulative decisions and outcomes

### User story format

File naming: `NN-story-name.md` — use a two-digit number prefix to control ordering (e.g. `01-user-authentication.md`, `02-password-reset.md`).

```markdown
---
story: <Story Name>
created: YYYY-MM-DD
---

## Description

<What needs to be built and why>

## Acceptance Criteria

- <criterion 1 — specific and testable>
- <criterion 2>
```

### Human gates

1. After `/agile:shape` — approve the sprint plan before executing
2. After `/agile:execute` — review the work and decide whether to run `/agile:review`
