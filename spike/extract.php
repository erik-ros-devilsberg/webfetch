<?php

declare(strict_types=1);

// Throwaway spike script: run readability.php + a \Dom\HTMLDocument baseline
// over the corpus, write per-page results, print a compact summary table.

require __DIR__ . '/vendor/autoload.php';

use fivefilters\Readability\Readability;
use fivefilters\Readability\Configuration;
use fivefilters\Readability\ParseException;

$manifest = json_decode(file_get_contents(__DIR__ . '/corpus/manifest.json'), true);
@mkdir(__DIR__ . '/results');
$results = [];

$words = fn (string $t): int => str_word_count(preg_replace('/\s+/u', ' ', $t) ?? '');

foreach ($manifest as $slug => $meta) {
    $file = __DIR__ . "/corpus/{$slug}.html";
    if (!is_file($file)) {
        continue;
    }
    $html = file_get_contents($file);
    $row = ['slug' => $slug, 'url' => $meta['url']];

    // --- readability.php pass ---
    try {
        $r = new Readability(new Configuration([
            'fixRelativeURLs' => true,
            'originalURL' => $meta['url'],
        ]));
        $r->parse($html);
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags((string) $r->getContent())));
        $row['readability'] = [
            'title'   => $r->getTitle(),
            'byline'  => $r->getAuthor(),
            'excerpt' => mb_substr((string) $r->getExcerpt(), 0, 120),
            'words'   => $words($text),
            'sample'  => mb_substr($text, 0, 200),
            'error'   => null,
        ];
    } catch (ParseException $e) {
        $row['readability'] = ['error' => $e->getMessage(), 'words' => 0, 'title' => null];
    } catch (\Throwable $e) {
        $row['readability'] = ['error' => get_class($e) . ': ' . $e->getMessage(), 'words' => 0, 'title' => null];
    }

    // --- \Dom\HTMLDocument baseline pass (PHP 8.4 lexbor parser) ---
    try {
        $doc = \Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR);
        $title = $doc->title;
        foreach ($doc->querySelectorAll('script, style, noscript') as $n) {
            $n->parentNode?->removeChild($n);
        }
        $bodyText = trim((string) preg_replace('/\s+/u', ' ', $doc->body?->textContent ?? ''));
        $row['lexbor'] = [
            'title' => $title,
            'body_words' => $words($bodyText),
            'error' => null,
        ];
    } catch (\Throwable $e) {
        $row['lexbor'] = ['error' => get_class($e) . ': ' . $e->getMessage(), 'body_words' => 0, 'title' => null];
    }

    $results[$slug] = $row;
    printf(
        "%-22s rb:%6dw lex:%6dw | %s%s\n",
        $slug,
        $row['readability']['words'] ?? 0,
        $row['lexbor']['body_words'] ?? 0,
        mb_substr((string) ($row['readability']['title'] ?? '(no title)'), 0, 60),
        isset($row['readability']['error']) && $row['readability']['error'] ? ' [RB ERR: ' . mb_substr($row['readability']['error'], 0, 60) . ']' : ''
    );
}

file_put_contents(__DIR__ . '/results/results.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo "\nFull results: spike/results/results.json\n";
