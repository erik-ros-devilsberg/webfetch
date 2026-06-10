<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Fetcher;

use Psr\SimpleCache\CacheInterface;

/**
 * Decorator: serves repeated fetches of the same URL from a PSR-16 cache.
 * Only successes are cached — failures should retry. Caching is opt-in:
 * wrap your fetcher explicitly.
 */
final class CachingFetcher implements Fetcher
{
    public function __construct(
        private readonly Fetcher $inner,
        private readonly CacheInterface $cache,
        private readonly int $ttlSeconds = 300,
    ) {
    }

    public function fetch(string $url, ?FetchOptions $options = null): FetchOutcome
    {
        $key = 'webfetch_' . sha1($url);

        $cached = $this->cache->get($key);
        if ($cached instanceof FetchSuccess) {
            return $cached;
        }

        $outcome = $this->inner->fetch($url, $options);
        if ($outcome instanceof FetchSuccess) {
            $this->cache->set($key, $outcome, $this->ttlSeconds);
        }

        return $outcome;
    }
}
