<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Tests\Support;

use Devilsberg\Webfetch\Fetcher\Fetcher;
use Devilsberg\Webfetch\Fetcher\FetchOptions;
use Devilsberg\Webfetch\Fetcher\FetchOutcome;
use Devilsberg\Webfetch\Fetcher\FetchSuccess;

/**
 * Records every call and serves a fixed success unless given outcomes.
 */
final class FakeFetcher implements Fetcher
{
    /** @var list<string> */
    public array $urls = [];

    /** @var list<FetchOutcome> */
    private array $queue = [];

    public function queue(FetchOutcome ...$outcomes): void
    {
        foreach ($outcomes as $outcome) {
            $this->queue[] = $outcome;
        }
    }

    public function calls(): int
    {
        return count($this->urls);
    }

    public function fetch(string $url, ?FetchOptions $options = null): FetchOutcome
    {
        $this->urls[] = $url;
        $next = array_shift($this->queue);

        return $next ?? new FetchSuccess(
            finalUrl: $url,
            status: 200,
            contentType: 'text/html',
            charset: 'utf-8',
            body: '<html><head><title>Fake</title></head><body><p>fake body</p></body></html>',
        );
    }
}
