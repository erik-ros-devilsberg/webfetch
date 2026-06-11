# The webfetch JSON contract

Machine-readable schema: [`schema/webfetch-success.schema.json`](../schema/webfetch-success.schema.json)
(draft-07). The test suite validates every success output against it.
**Schema changes are breaking changes** — bump the major version.

## Success shape (`ok: true`, `schema_version: 2`)

| Field | Type | Meaning |
|-------|------|---------|
| `ok` | `true` | Discriminator — success and error outputs share this single field. |
| `schema_version` | `2` | Contract version. |
| `url` | string | Final URL after redirects. |
| `fetched_at` | string (RFC 3339) | When the page was fetched. |
| `source_type` | `"html"` \| `"pdf"` | Which source format the content was extracted from. |
| `title` | string \| null | Page title (readability → og:title → `<title>`). |
| `byline` | string \| null | Author via readability's detection, suppressed when it merely echoes site-wide `meta[name=author]` on a non-article page. Null when unknown — honest nulls over site chrome. |
| `lang` | string \| null | `<html lang>` attribute. |
| `published_at` | string \| null | `article:published_time` meta, verbatim. |
| `excerpt` | string \| null | og:description → meta description. Deterministic — never synthesized from content. |
| `content_markdown` | string | Main content as markdown (headings, lists, links, code, emphasis preserved; scripts/styles stripped). |
| `word_count` | integer | Words in the extracted text. |
| `links` | array of `{text, href}` | Content links (readability path) or headline links (fallback path), absolute URLs, deduped, max 100. |
| `meta` | object | `site_name`, `description`, `image`, `type` — each string \| null, from og:* tags. |
| `extraction_strategy` | `"readability"` \| `"fallback"` \| `"pdf"` | How content was obtained (see below). |

All fields are always present; missing data is `null` (or `[]` for links),
never an absent key.

## Error shape (`ok: false`, `schema_version: 2`)

Machine-readable schema: [`schema/webfetch-error.schema.json`](../schema/webfetch-error.schema.json).
The public entry point (`Webfetch::create()->fetch($url)`) never throws for
pipeline failures — it returns this shape instead.

| Field | Type | Meaning |
|-------|------|---------|
| `ok` | `false` | Discriminator. |
| `schema_version` | `2` | Contract version. |
| `url` | string | The requested URL (final URL when failure happened after redirects). |
| `error_code` | string enum | See below. |
| `message` | string | Human-readable detail. |
| `http_status` | integer \| null | Set for `http_client_error` / `http_server_error`, null elsewhere. |

### Error codes

| Code | Meaning |
|------|---------|
| `invalid_url` | Input is not a syntactically valid http(s) URL. |
| `connection_failed` | DNS failure, connection refused, or other transport error. |
| `timeout` | Connect or total timeout exceeded. |
| `too_many_redirects` | Redirect chain exceeded the configured limit. |
| `response_too_large` | Body exceeded the size cap (aborted mid-stream). |
| `http_client_error` | HTTP 4xx. |
| `http_server_error` | HTTP 5xx. |
| `not_html` | Content type has no registered extractor (HTML and PDF are supported; JSON/images/etc. are not). |
| `browser_unavailable` | ChromeFetcher only: chrome-php/chrome not installed or Chrome failed to start. |
| `blocked_url` | SSRF guard (on by default): target is private/loopback/link-local, directly or via redirect. `FetchOptions(allowPrivateTargets: true)` disables. |
| `robots_disallowed` | Only with the opt-in `RobotsAwareFetcher`: robots.txt disallows the path. |
| `empty_body` | HTTP 200 but the body is empty/whitespace. |
| `parse_failure` | The HTML parser failed (near-unreachable with lexbor; reserved). |
| `empty_extraction` | Markup parsed but yielded no title, description, content, or links — typically a JavaScript-rendered shell. |

`empty_body` vs `empty_extraction`: the former means the server sent
nothing; the latter means it sent markup with nothing readable in it (the
SPA case — a headless-browser fetcher is the roadmap answer).

## Extraction strategies

1. **readability** — fivefilters/readability.php found a main content block
   holding ≥30 words and ≥10% of the page's body text.
2. **fallback** — readability found nothing trustworthy (index pages, thin
   landing pages). Output is built from title + meta description + headline
   links (anchor text ≥15 chars). If even that yields nothing, the result
   is an `empty_extraction` error instead (see story 06 — error schema
   lands with the error-contract sprint).
3. **pdf** — the source was `application/pdf`; text and `title`/`byline`
   metadata are extracted (in an isolated subprocess for safety). A
   text-empty or scanned/image-only PDF yields `empty_extraction`.
