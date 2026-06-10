<?php

declare(strict_types=1);

// Composing fetcher decorators: cache + per-host rate limiting around the
// static fetcher. The same URL is fetched twice — the counter proves the
// second one never reached the network layer.
//
//   php examples/04-decorators.php https://en.wikipedia.org/wiki/PHP

require __DIR__ . '/../vendor/autoload.php';

use Devilsberg\Webfetch\Fetcher\CachingFetcher;
use Devilsberg\Webfetch\Fetcher\Fetcher;
use Devilsberg\Webfetch\Fetcher\FetchOptions;
use Devilsberg\Webfetch\Fetcher\FetchOutcome;
use Devilsberg\Webfetch\Fetcher\RateLimitedFetcher;
use Devilsberg\Webfetch\Fetcher\StaticFetcher;
use Devilsberg\Webfetch\Webfetch;
use Psr\SimpleCache\CacheInterface;

/**
 * Counts how often the wrapped fetcher actually gets asked.
 */
final class CountingFetcher implements Fetcher
{
    public int $calls = 0;

    public function __construct(private readonly Fetcher $inner)
    {
    }

    public function fetch(string $url, ?FetchOptions $options = null): FetchOutcome
    {
        ++$this->calls;

        return $this->inner->fetch($url, $options);
    }
}

/**
 * Toy PSR-16 cache for the demo — use a real one (Symfony Cache, etc.) in
 * production.
 */
final class DemoCache implements CacheInterface
{
    /** @var array<string, mixed> */
    private array $items = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    public function set(string $key, mixed $value, \DateInterval|int|null $ttl = null): bool
    {
        $this->items[$key] = $value;

        return true;
    }

    public function delete(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    public function clear(): bool
    {
        $this->items = [];

        return true;
    }

    /**
     * @param iterable<string> $keys
     *
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, \DateInterval|int|null $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            $this->set((string) $key, $value, $ttl);
        }

        return true;
    }

    /**
     * @param iterable<string> $keys
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }

        return true;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->items);
    }
}

$url = $argv[1] ?? null;
if ($url === null) {
    fwrite(STDERR, "Usage: php examples/04-decorators.php <url>\n");
    exit(1);
}

$counter = new CountingFetcher(new StaticFetcher());
$fetcher = new CachingFetcher(
    new RateLimitedFetcher($counter, minIntervalSeconds: 1.0),
    new DemoCache(),
    ttlSeconds: 300,
);
$webfetch = Webfetch::create(fetcher: $fetcher);

foreach ([1, 2] as $attempt) {
    $start = microtime(true);
    /** @var array<string, mixed> $result */
    $result = json_decode($webfetch->fetch($url), true, 512, JSON_THROW_ON_ERROR);
    printf(
        "fetch #%d: ok=%s, %.0f ms, network calls so far: %d\n",
        $attempt,
        $result['ok'] === true ? 'true' : 'false',
        (microtime(true) - $start) * 1000,
        $counter->calls,
    );
}
