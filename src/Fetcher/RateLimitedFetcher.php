<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Fetcher;

/**
 * Decorator: enforces a minimum interval between requests to the same
 * host, so loops over many URLs from one site do not hammer it. Clock and
 * sleep are injectable for instant tests.
 */
final class RateLimitedFetcher implements Fetcher
{
    /** @var array<string, float> host → time of last request */
    private array $lastRequestAt = [];

    private readonly \Closure $now;

    private readonly \Closure $sleep;

    /**
     * @param \Closure(): float     $now   returns the current time in seconds
     * @param \Closure(float): void $sleep blocks for the given seconds
     */
    public function __construct(
        private readonly Fetcher $inner,
        private readonly float $minIntervalSeconds = 1.0,
        ?\Closure $now = null,
        ?\Closure $sleep = null,
    ) {
        $this->now = $now ?? static fn (): float => microtime(true);
        $this->sleep = $sleep ?? static function (float $seconds): void {
            usleep((int) ($seconds * 1_000_000));
        };
    }

    public function fetch(string $url, ?FetchOptions $options = null): FetchOutcome
    {
        $host = parse_url($url, PHP_URL_HOST);
        $host = is_string($host) ? strtolower($host) : '';

        $last = $this->lastRequestAt[$host] ?? null;
        if ($last !== null) {
            $elapsed = ($this->now)() - $last;
            if ($elapsed < $this->minIntervalSeconds) {
                ($this->sleep)($this->minIntervalSeconds - $elapsed);
            }
        }
        $this->lastRequestAt[$host] = ($this->now)();

        return $this->inner->fetch($url, $options);
    }
}
