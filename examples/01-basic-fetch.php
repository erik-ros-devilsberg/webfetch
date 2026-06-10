<?php

declare(strict_types=1);

// Basic usage: URL in, readable-content JSON out.
//
//   php examples/01-basic-fetch.php https://en.wikipedia.org/wiki/PHP

require __DIR__ . '/../vendor/autoload.php';

use Devilsberg\Webfetch\Webfetch;

$url = $argv[1] ?? null;
if ($url === null) {
    fwrite(STDERR, "Usage: php examples/01-basic-fetch.php <url>\n");
    exit(1);
}

$json = Webfetch::create()->fetch($url);

// fetch() always returns valid JSON — pretty-print it for reading.
echo json_encode(
    json_decode($json, false, 512, JSON_THROW_ON_ERROR),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
), "\n";
