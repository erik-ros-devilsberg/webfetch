<?php

declare(strict_types=1);

// The never-throws guarantee: every failure is JSON with an error_code.
// Runs a deliberately broken URL, then (optionally) one you pass in.
//
//   php examples/02-error-handling.php [url]

require __DIR__ . '/../vendor/autoload.php';

use Devilsberg\Webfetch\Webfetch;

/**
 * @param array<string, mixed> $result
 */
function describe(array $result): string
{
    if ($result['ok'] === true) {
        return sprintf(
            "OK: \"%s\" — %d words of markdown",
            is_string($result['title']) ? $result['title'] : '(untitled)',
            is_int($result['word_count']) ? $result['word_count'] : 0,
        );
    }

    $code = is_string($result['error_code']) ? $result['error_code'] : 'unknown';

    // Branch on the machine-readable code, not the human message.
    return match ($code) {
        'invalid_url' => 'That was not a fetchable http(s) URL.',
        'blocked_url' => 'Refused: the target is a private/internal address.',
        'timeout' => 'The server was too slow.',
        'empty_extraction' => 'Page loaded but had no readable content (likely a JS-rendered app).',
        default => "Failed with code '{$code}': " . (is_string($result['message']) ? $result['message'] : ''),
    };
}

$webfetch = Webfetch::create();

foreach (array_filter(['definitely not a url', $argv[1] ?? null]) as $url) {
    /** @var array<string, mixed> $result */
    $result = json_decode($webfetch->fetch($url), true, 512, JSON_THROW_ON_ERROR);
    echo $url, "\n  → ", describe($result), "\n";
}
