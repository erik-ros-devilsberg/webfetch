<?php

declare(strict_types=1);

// Throwaway: print the full markdown for one fixture.

require dirname(__DIR__) . '/vendor/autoload.php';

use Devilsberg\Webfetch\Extractor\Extractor;
use Devilsberg\Webfetch\Extractor\ExtractSuccess;
use Devilsberg\Webfetch\Fetcher\FetchSuccess;

$file = $argv[1] ?? 'highlighted-code.html';
$html = file_get_contents(dirname(__DIR__) . '/tests/fixtures/' . $file);
if ($html === false) {
    exit(1);
}
$outcome = new Extractor()->extract(new FetchSuccess('https://example.com/x', 200, 'text/html', 'utf-8', $html));
if ($outcome instanceof ExtractSuccess) {
    echo $outcome->content->contentMarkdown, "\n";
}
