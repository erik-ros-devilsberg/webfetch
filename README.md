# devilsberg/webfetch

Turn a webpage into readable-content JSON. Built for AI agents, usable
anywhere.

> **Status: release-ready, pending first publish to Packagist.** See
> `CHANGELOG.md` and `docs/RELEASING.md`.

## What it does

Give it a URL, get back one JSON document with the page's readable content:
title, byline, language, published date, the main content as markdown,
links, and metadata. Failures come back as JSON too — agents always get
something parseable.

Extraction reuses [fivefilters/readability.php](https://github.com/fivefilters/readability.php);
parsing uses PHP 8.4's lexbor-based `\Dom\HTMLDocument`. Fetching is
static-first; JavaScript-rendered pages are covered by the optional
headless-Chrome fetcher (see [SPAs](#javascript-rendered-pages-spas)).
`application/pdf` URLs work too — text and metadata come back in the same
JSON shape, with `source_type: "pdf"` (see [PDFs](#pdfs)).

## Requirements

- PHP `^8.4`
- [Composer](https://getcomposer.org/)

## Install

```sh
composer require devilsberg/webfetch   # available once v1.0.0 is on Packagist
```

## Why this and not …?

- **readability.php alone** — gives you a DOM and HTML content; webfetch
  adds the fetch layer (timeouts, size caps, SSRF guard), markdown
  conversion, metadata, links, and a stable JSON contract with error codes.
  We reuse readability.php internally rather than compete with it.
- **A headless-browser stack for everything** — seconds of latency and
  hundreds of MB per page that plain HTTP would have served in 200ms.
  webfetch is static-first, browser-only-on-demand.
- **Rolling your own with Guzzle + strip_tags** — works until the first
  cookie banner, charset surprise, redirect loop, or 169.254.169.254.
  That plumbing is exactly what this package is.

## Usage

```php
use Devilsberg\Webfetch\Webfetch;

$json = Webfetch::create()->fetch('https://example.com/article');
// → {"ok": true, "title": ..., "content_markdown": ..., ...}
// or {"ok": false, "error_code": "timeout", ...} — it never throws.
```

Output shapes are a versioned, schema-validated contract — see
[docs/json-schema.md](docs/json-schema.md) and [schema/](schema/).
Runnable scripts for every pattern below live in [examples/](examples/) —
they are lint- and PHPStan-gated, so they stay in sync with the code.

### Command line

```sh
vendor/bin/webfetch https://example.com/article
vendor/bin/webfetch --timeout=10 --max-bytes=2000000 https://example.com/article
```

Prints exactly one JSON document to stdout (diagnostics go to stderr).
Exit codes: `0` success, `1` fetch/extract error (error JSON still printed),
`2` usage error. `--help` lists all flags.

### JavaScript-rendered pages (SPAs)

The default static fetcher cannot see content that JavaScript injects —
those pages come back as an `empty_extraction` error. For them, install
the optional headless-Chrome backend (plus a Chrome/Chromium binary):

```sh
composer require chrome-php/chrome
```

```php
use Devilsberg\Webfetch\Fetcher\ChromeFetcher;
use Devilsberg\Webfetch\Webfetch;

// Static first — fall back to Chrome only when extraction came up empty.
$json = Webfetch::create()->fetch($url);
$result = json_decode($json, true);
if (($result['error_code'] ?? null) === 'empty_extraction') {
    $json = Webfetch::create(fetcher: new ChromeFetcher())->fetch($url);
}
```

`ChromeFetcher` accepts a binary path, wait strategy (`load` /
`networkIdle`), an extra render delay, and a profile directory. Without
chrome-php installed it returns a `browser_unavailable` error JSON — it
never throws.

### PDFs

A URL that serves `application/pdf` is extracted to the same JSON shape as a
web page — text as `content_markdown`, plus `title`/`byline` from the PDF's
metadata — tagged `source_type: "pdf"`. No extra setup: the
`smalot/pdfparser` dependency is pulled in automatically, and no system
binary is needed. Note that `smalot/pdfparser` is **LGPL-3.0** — your MIT
code calling it stays MIT, but if you redistribute a bundle (committed
`vendor/`, phar, Docker image) you carry its source and notices for those
files; plain Composer installs and server-side use don't.

```php
$json = Webfetch::create()->fetch('https://example.com/report.pdf');
// { "ok": true, "source_type": "pdf", "title": "...", "content_markdown": "...", ... }
```

Parsing runs in an isolated child process with a hard memory cap and a
wall-clock timeout, so a malicious or malformed PDF (decompression bomb,
corrupted structure) can never crash the caller — it comes back as an error
JSON instead. Scanned or image-only PDFs (no embedded text) return an
`empty_extraction` error; there is no OCR.

## Safety and etiquette

- **SSRF guard, on by default**: URLs pointing at private, loopback, or
  link-local targets (directly or via redirect) are refused with a
  `blocked_url` error. Agents get pointed at attacker-chosen URLs;
  internal services should not be reachable through them. Opt out with
  `new FetchOptions(allowPrivateTargets: true)`. The check is best-effort
  (documented in `UrlGuard`).
- **Opt-in decorators** — wrap any `Fetcher`:
  - `CachingFetcher($inner, $psr16Cache, ttlSeconds: 300)` — successes only
  - `RateLimitedFetcher($inner, minIntervalSeconds: 1.0)` — per-host spacing
  - `RobotsAwareFetcher($inner)` — honors `User-agent: *` Disallow rules
    (`robots_disallowed` error). Off by default: a user-directed single
    fetch is browser-like traffic; wrap when doing crawling-shaped work.

## Development

| Command            | What it does                                                  |
| ------------------ | ------------------------------------------------------------- |
| `composer test`    | Run the PHPUnit test suite.                                   |
| `composer lint`    | Check code style with PHP-CS-Fixer (dry run, exits non-zero). |
| `composer fix`     | Apply PHP-CS-Fixer fixes in place.                            |
| `composer analyse` | Run PHPStan static analysis at level 8.                       |
| `composer check`   | Run `lint` + `analyse` + `test` as a single pre-commit gate.  |

The test suite includes an extraction-quality gate: a committed corpus of
real (freely-licensed) pages must stay ≥80% "usable" against
`tests/corpus/baseline.json`, and no page may regress. If your change
breaks it, extraction quality went down — fix the change, not the baseline.

## License

[MIT](LICENSE)
