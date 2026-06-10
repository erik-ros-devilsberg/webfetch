<?php

declare(strict_types=1);

// Throwaway: fetch the redistributable corpus pages committed under
// tests/corpus/. Only freely-licensed sources belong here — see
// tests/corpus/ATTRIBUTION.md.

$corpus = [
    'wikipedia-web-scraping' => 'https://en.wikipedia.org/wiki/Web_scraping',
    'wikipedia-nl-php'       => 'https://nl.wikipedia.org/wiki/PHP',
    'whatwg-intro'           => 'https://html.spec.whatwg.org/multipage/introduction.html',
    'php-manual-htmldocument'=> 'https://www.php.net/manual/en/class.dom-htmldocument.php',
    'keepachangelog'         => 'https://keepachangelog.com/en/1.1.0/',
    'wikipedia-html'         => 'https://en.wikipedia.org/wiki/HTML',
    'wikipedia-json'         => 'https://en.wikipedia.org/wiki/JSON',
];

$dir = dirname(__DIR__) . '/tests/corpus';
@mkdir($dir);

foreach ($corpus as $slug => $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'devilsberg-webfetch corpus collector (one-off, attributed)',
        CURLOPT_ENCODING => '',
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if (is_string($body) && $status === 200) {
        file_put_contents("{$dir}/{$slug}.html", $body);
        printf("%-26s %d %8d bytes\n", $slug, $status, strlen($body));
    } else {
        printf("%-26s FAILED (%d)\n", $slug, $status);
    }
}
