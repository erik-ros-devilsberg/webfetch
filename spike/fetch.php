<?php

declare(strict_types=1);

// Throwaway spike script: fetch the corpus, save raw HTML + a manifest.

$corpus = [
    'wikipedia-en'        => 'https://en.wikipedia.org/wiki/Web_scraping',
    'wikipedia-nl'        => 'https://nl.wikipedia.org/wiki/PHP',
    'php-docs'            => 'https://www.php.net/manual/en/class.dom-htmldocument.php',
    'mdn-fetch'           => 'https://developer.mozilla.org/en-US/docs/Web/API/Fetch_API',
    'fowler-microservices'=> 'https://martinfowler.com/articles/microservices.html',
    'paulgraham-essay'    => 'https://paulgraham.com/greatwork.html',
    'daringfireball'      => 'https://daringfireball.net/projects/markdown/',
    'stackoverflow'       => 'https://stackoverflow.com/questions/11227809/why-is-processing-a-sorted-array-faster-than-processing-an-unsorted-array',
    'hackernews'          => 'https://news.ycombinator.com/',
    'bbc-news-index'      => 'https://www.bbc.com/news',
    'guardian-index'      => 'https://www.theguardian.com/international',
    'nu-nl-index'         => 'https://www.nu.nl/',
    'github-repo'         => 'https://github.com/php/php-src',
    'react-docs'          => 'https://react.dev/learn',
    'excalidraw-spa'      => 'https://excalidraw.com/',
    'notion-landing'      => 'https://www.notion.so/',
    'whatwg-spec'         => 'https://html.spec.whatwg.org/multipage/introduction.html',
    'keepachangelog'      => 'https://keepachangelog.com/en/1.1.0/',
    'wordpress-blog'      => 'https://wordpress.org/news/',
    'arstechnica-index'   => 'https://arstechnica.com/',
];

@mkdir(__DIR__ . '/corpus');
$manifest = [];

foreach ($corpus as $slug => $url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36 webfetch-spike',
        CURLOPT_ENCODING       => '',
    ]);
    $body = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $ctype = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $err = curl_error($ch);
    curl_close($ch);

    $bytes = is_string($body) ? strlen($body) : 0;
    if (is_string($body) && $bytes > 0) {
        file_put_contents(__DIR__ . "/corpus/{$slug}.html", $body);
    }
    $manifest[$slug] = [
        'url' => $url,
        'status' => $status,
        'content_type' => $ctype,
        'bytes' => $bytes,
        'error' => $err ?: null,
    ];
    printf("%-22s %3d %-30s %8d %s\n", $slug, $status, substr($ctype, 0, 30), $bytes, $err);
}

file_put_contents(__DIR__ . '/corpus/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
