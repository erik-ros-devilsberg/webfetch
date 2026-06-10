<?php

declare(strict_types=1);

// Throwaway: print extraction stats for every corpus + fixture page so the
// committed baseline.json reflects real numbers, not guesses.

require dirname(__DIR__) . '/vendor/autoload.php';

use Devilsberg\Webfetch\Extractor\Extractor;
use Devilsberg\Webfetch\Extractor\ExtractSuccess;
use Devilsberg\Webfetch\Fetcher\FetchSuccess;

$pages = [
    'fixtures/article.html'              => 'https://example.com/article',
    'fixtures/docs.html'                 => 'https://example.com/docs',
    'fixtures/minimal.html'              => 'https://example.com/minimal',
    'fixtures/listing-trap.html'         => 'https://example.com/listing',
    'corpus/wikipedia-web-scraping.html' => 'https://en.wikipedia.org/wiki/Web_scraping',
    'corpus/wikipedia-nl-php.html'       => 'https://nl.wikipedia.org/wiki/PHP',
    'corpus/whatwg-intro.html'           => 'https://html.spec.whatwg.org/multipage/introduction.html',
    'corpus/php-manual-htmldocument.html'=> 'https://www.php.net/manual/en/class.dom-htmldocument.php',
    'corpus/keepachangelog.html'         => 'https://keepachangelog.com/en/1.1.0/',
    'corpus/wikipedia-html.html'         => 'https://en.wikipedia.org/wiki/HTML',
    'corpus/wikipedia-json.html'         => 'https://en.wikipedia.org/wiki/JSON',
];

$extractor = new Extractor();
foreach ($pages as $file => $url) {
    $path = dirname(__DIR__) . '/tests/' . $file;
    $html = file_get_contents($path);
    if ($html === false) {
        printf("%-38s MISSING\n", $file);
        continue;
    }
    $outcome = $extractor->extract(new FetchSuccess($url, 200, 'text/html', 'utf-8', $html));
    if (!$outcome instanceof ExtractSuccess) {
        printf("%-38s FAILURE\n", $file);
        continue;
    }
    $c = $outcome->content;
    printf(
        "%-38s %-11s words=%-6d title=%s\n",
        $file,
        $c->strategy->value,
        $c->wordCount,
        mb_substr((string) $c->title, 0, 45),
    );
}
