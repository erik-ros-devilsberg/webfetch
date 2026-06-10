---
story: Extraction quality fixes — code blocks, tables, byline
created: 2026-06-10
---

## Description

Live smoke testing against a Packagist package page, compared with Anthropic's
WebFetch as the quality reference, exposed three defects in our extraction
where the reference is clean:

1. **Code blocks leak markup.** Pages with server-side syntax highlighting
   (Packagist, GitHub-rendered READMEs) carry `<span>` tags inside
   `<pre>`/`<code>`; our converter preserves code content verbatim, so the
   markup pollutes every code sample in `content_markdown`.
2. **Tables flatten to mush.** league/html-to-markdown ships a table
   converter that we never enabled — `Key | Default | Description` becomes
   `KeyDefaultDescription`.
3. **Byline trusts site chrome.** `meta[name=author]` on Packagist is the
   site's author ("Jordi Boggiano"), not the page's. Confident garbage is
   worse than null for agent consumption.

Explicitly out of scope: badge/image URL noise — the reference output keeps
it too (parity), and images can be legitimate content.

## Acceptance Criteria

- Code blocks in `content_markdown` contain plain text only: all HTML tags
  inside `<pre>` and `<code>` elements are stripped before markdown
  conversion (entities decoded, text preserved)
- HTML tables convert to markdown tables (league table converter enabled);
  a fixture with a table proves it
- Byline source chain is readability's own byline detection only; the
  `meta[name=author]` fallback is removed — byline is null when readability
  finds none. `docs/json-schema.md` byline row updated accordingly
- A corpus page reproducing all three defects (Packagist-style: highlighted
  code spans, a table, a misleading site-wide meta author) is added to
  `tests/corpus/` with baseline expectations that fail on any of the three
  defects — synthetic twin is acceptable if committing Packagist HTML is
  undesirable
- Existing corpus baseline does not regress
- `composer check` and `composer audit` pass
