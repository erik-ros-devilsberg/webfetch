<?php

declare(strict_types=1);

// Static-first, browser-on-demand: try the cheap fetch, escalate to
// headless Chrome only when the page turned out to be a JS-rendered shell.
// Needs `composer require chrome-php/chrome` plus a Chrome/Chromium binary
// for the escalation path — degrades to a message without them.
//
//   php examples/03-spa-fallback.php https://excalidraw.com/

require __DIR__ . '/../vendor/autoload.php';

use Devilsberg\Webfetch\Fetcher\ChromeFetcher;
use Devilsberg\Webfetch\Webfetch;

$url = $argv[1] ?? null;
if ($url === null) {
    fwrite(STDERR, "Usage: php examples/03-spa-fallback.php <url>\n");
    exit(1);
}

$json = Webfetch::create()->fetch($url);
/** @var array<string, mixed> $result */
$result = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

if (($result['error_code'] ?? null) === 'empty_extraction') {
    echo "Static fetch found no readable content — retrying through headless Chrome...\n";
    $json = Webfetch::create(fetcher: new ChromeFetcher())->fetch($url);
    /** @var array<string, mixed> $result */
    $result = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

    if (($result['error_code'] ?? null) === 'browser_unavailable') {
        echo "Chrome is not available: ", is_string($result['message']) ? $result['message'] : '', "\n";
        exit(1);
    }
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), "\n";
