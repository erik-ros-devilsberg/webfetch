# Spike findings — readability.php + \Dom\HTMLDocument (Phase 0)

Status: **complete** (2026-06-10). Spike code in `spike/` (throwaway, not for `src/`).

## Rating rubric

Each corpus page's extraction is rated against what a human reader would
call "the content of this page". This rubric is reused later as the Phase 5
scored corpus gate.

- **usable** — title is correct; extracted text contains the main content
  substantially complete (≳80% of the readable body); no significant
  boilerplate (nav, cookie banners, footers) polluting the text. An agent
  could answer questions about the page from this output alone.
- **degraded** — title or content partially correct: main content truncated,
  significant boilerplate included, or metadata (byline/date) wrong — but an
  agent would still get real value from the output.
- **failed** — empty or near-empty extraction, wrong content extracted, or
  a parse/runtime error. An agent gets nothing trustworthy.

Index/landing pages (no single "main article") rate **usable** if the
extraction captures the page's headline links/teasers coherently, **degraded**
if it captures some, **failed** if effectively empty.

## readability.php PHP 8.4 compatibility

**Confirmed compatible.** `fivefilters/readability.php` v3.3.3 installs and
runs cleanly on PHP 8.4.18 — no install conflicts, no deprecation notices,
no runtime errors across the corpus. It parses via `masterminds/html5`
(its own HTML5 parser returning legacy `DOMDocument` objects), so it neither
uses nor needs `\Dom\HTMLDocument` internally; that stays an internal detail
behind our API.

One behavior to design around: near-empty input (JS shells) throws
`ParseException("Could not parse text.")` — our wrapper must catch it and map
it to the empty-extraction error code (story 06).

## Per-page results

Corpus: 20 live pages fetched 2026-06-10 (raw HTML in `spike/corpus/`,
full extraction output in `spike/results/results.json`).

| Page | Type | rb words | Rating | Note |
|------|------|---------:|--------|------|
| wikipedia-en | article | 3861 | usable | clean full extraction |
| wikipedia-nl | article (NL) | 1140 | degraded | infobox noise, body partially truncated |
| php-docs | docs | 261 | usable | class synopsis = the page's content |
| mdn-fetch | docs | 377 | usable | overview content captured |
| fowler-microservices | long article | 5294 | usable | full body, sidebars stripped |
| paulgraham-essay | essay, old HTML | 11734 | usable | essentially lossless |
| daringfireball | docs/blog | 1071 | usable | |
| stackoverflow | Q&A | 1130 | degraded | top answer only; question + other answers dropped |
| hackernews | index, minimal HTML | 547 | usable | link list coherent |
| bbc-news-index | news index | 918 | usable | headlines + teasers |
| guardian-index | news index | 1981 | usable | headlines + teasers |
| nu-nl-index | news index (NL) | 0 | failed | ~8KB JS/consent shell — SPA-phase data |
| github-repo | app-ish page | 630 | usable | README extracted |
| react-docs | modern SSG docs | 1991 | usable | |
| excalidraw-spa | SPA | 0 | failed | expected — canvas app, empty shell |
| notion-landing | marketing | 143 | degraded | thin but real copy |
| whatwg-spec | huge spec page | 8561 | usable | |
| keepachangelog | simple docs | 1051 | usable | |
| wordpress-blog | news listing | 26 | failed | extracted footer ("Contribute") instead of posts |
| arstechnica-index | news index | 155 | degraded | about-blurb instead of headlines |

**Score: 13 usable / 4 degraded / 3 failed.**
Excluding the 2 expected JS-shell failures: 13/18 usable (72%), 1 genuine
failure (wordpress-blog). On the article/docs subset — the primary agent
use case — 10/12 usable (83%), 2 degraded, 0 failed.

## \Dom\HTMLDocument broken-HTML assessment

**Held up.** `\Dom\HTMLDocument::createFromString()` parsed all 20
real-world pages — including legacy markup (paulgraham.com), a 1.2MB
StackOverflow page, and a 1.5MB Guardian page — without a single fatal
error or crash. Titles and body text were retrievable everywhere a body
existed. Suitable as the production parsing layer (stories 04+).

## Recommendation

**GO — reuse `fivefilters/readability.php`.** Rationale:

- Zero PHP 8.4 compatibility issues; actively maintained (v3.3.3).
- Extraction quality on the primary use case (articles/docs) is at the
  83% usable level out of the box, before any fallback heuristics.
- Building our own extractor would mean re-implementing Mozilla's
  Readability scoring for, at best, parity.

Required mitigations (already reflected in stories 05/06):

- Wrap `ParseException` → empty-extraction error JSON.
- Fallback heuristics for index/landing pages where readability's
  main-content bias picks the wrong block (wordpress-blog, arstechnica) —
  e.g. fall back to title + meta description + headline links when the
  extracted text is a tiny fraction of the page's body text (the
  rb-words vs body-words ratio is a cheap, effective signal).
- JS shells (nu.nl, excalidraw) are unfixable statically — confirms the
  Phase 4 Chrome fetcher and the static-first-with-fallback pattern.
- StackOverflow-style Q&A loses answers beyond the top one — acceptable
  for v1, candidate for a site-specific tweak later.
