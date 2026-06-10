# The webfetch JSON contract

Machine-readable schema: [`schema/webfetch-success.schema.json`](../schema/webfetch-success.schema.json)
(draft-07). The test suite validates every success output against it.
**Schema changes are breaking changes** — bump the major version.

## Success shape (`ok: true`, `schema_version: 1`)

| Field | Type | Meaning |
|-------|------|---------|
| `ok` | `true` | Discriminator — success and error outputs share this single field. |
| `schema_version` | `1` | Contract version. |
| `url` | string | Final URL after redirects. |
| `fetched_at` | string (RFC 3339) | When the page was fetched. |
| `title` | string \| null | Page title (readability → og:title → `<title>`). |
| `byline` | string \| null | Author (readability → `meta[name=author]`). |
| `lang` | string \| null | `<html lang>` attribute. |
| `published_at` | string \| null | `article:published_time` meta, verbatim. |
| `excerpt` | string \| null | og:description → meta description. Deterministic — never synthesized from content. |
| `content_markdown` | string | Main content as markdown (headings, lists, links, code, emphasis preserved; scripts/styles stripped). |
| `word_count` | integer | Words in the extracted text. |
| `links` | array of `{text, href}` | Content links (readability path) or headline links (fallback path), absolute URLs, deduped, max 100. |
| `meta` | object | `site_name`, `description`, `image`, `type` — each string \| null, from og:* tags. |
| `extraction_strategy` | `"readability"` \| `"fallback"` | How content was obtained (see below). |

All fields are always present; missing data is `null` (or `[]` for links),
never an absent key.

## Extraction strategies

1. **readability** — fivefilters/readability.php found a main content block
   holding ≥30 words and ≥10% of the page's body text.
2. **fallback** — readability found nothing trustworthy (index pages, thin
   landing pages). Output is built from title + meta description + headline
   links (anchor text ≥15 chars). If even that yields nothing, the result
   is an `empty_extraction` error instead (see story 06 — error schema
   lands with the error-contract sprint).
