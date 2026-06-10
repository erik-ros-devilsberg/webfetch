# devilsberg/webfetch

Turn a webpage into readable-content JSON. Built for AI agents, usable
anywhere.

> **Status: pre-release.** The API is under construction — see
> `docs/roadmap.md` for where this is going. Nothing below is published to
> Packagist yet.

## What it does

Give it a URL, get back one JSON document with the page's readable content:
title, byline, language, published date, the main content as markdown,
links, and metadata. Failures come back as JSON too — agents always get
something parseable.

Extraction reuses [fivefilters/readability.php](https://github.com/fivefilters/readability.php);
parsing uses PHP 8.4's lexbor-based `\Dom\HTMLDocument`. JavaScript-rendered
pages are out of scope for the static fetcher; a headless-Chrome fetcher
behind the same interface is on the roadmap.

## Requirements

- PHP `^8.4`
- [Composer](https://getcomposer.org/)

## Install

```sh
composer require devilsberg/webfetch   # not yet published — coming with v1.0.0
```

## Usage

```php
use Devilsberg\Webfetch\Webfetch;

$json = Webfetch::create()->fetch('https://example.com/article');
// → {"ok": true, "title": ..., "content_markdown": ..., ...}
// or {"ok": false, "error_code": "timeout", ...} — it never throws.
```

Output shapes are a versioned, schema-validated contract — see
[docs/json-schema.md](docs/json-schema.md) and [schema/](schema/).

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
if (json_decode($json, true)['error_code'] ?? null === 'empty_extraction') {
    $json = Webfetch::create(fetcher: new ChromeFetcher())->fetch($url);
}
```

`ChromeFetcher` accepts a binary path, wait strategy (`load` /
`networkIdle`), an extra render delay, and a profile directory. Without
chrome-php installed it returns a `browser_unavailable` error JSON — it
never throws.

## Development

| Command            | What it does                                                  |
| ------------------ | ------------------------------------------------------------- |
| `composer test`    | Run the PHPUnit test suite.                                   |
| `composer lint`    | Check code style with PHP-CS-Fixer (dry run, exits non-zero). |
| `composer fix`     | Apply PHP-CS-Fixer fixes in place.                            |
| `composer analyse` | Run PHPStan static analysis at level 8.                       |
| `composer check`   | Run `lint` + `analyse` + `test` as a single pre-commit gate.  |

## License

[MIT](LICENSE)
