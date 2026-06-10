<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Tests\Fetcher;

use Devilsberg\Webfetch\Fetcher\RateLimitedFetcher;
use Devilsberg\Webfetch\Tests\Support\FakeFetcher;
use PHPUnit\Framework\TestCase;

final class RateLimitedFetcherTest extends TestCase
{
    /** @var list<float> */
    private array $sleeps = [];

    private float $clock = 1000.0;

    private function fetcher(FakeFetcher $inner, float $minInterval): RateLimitedFetcher
    {
        return new RateLimitedFetcher(
            $inner,
            minIntervalSeconds: $minInterval,
            now: fn (): float => $this->clock,
            sleep: function (float $seconds): void {
                $this->sleeps[] = $seconds;
                $this->clock += $seconds;
            },
        );
    }

    public function testFirstRequestIsNotDelayed(): void
    {
        $fetcher = $this->fetcher(new FakeFetcher(), 1.0);

        $fetcher->fetch('https://example.com/a');

        self::assertSame([], $this->sleeps);
    }

    public function testSameHostWithinIntervalSleeps(): void
    {
        $fetcher = $this->fetcher(new FakeFetcher(), 2.0);

        $fetcher->fetch('https://example.com/a');
        $this->clock += 0.5;
        $fetcher->fetch('https://example.com/b');

        self::assertCount(1, $this->sleeps);
        self::assertEqualsWithDelta(1.5, $this->sleeps[0], 0.001);
    }

    public function testSameHostAfterIntervalDoesNotSleep(): void
    {
        $fetcher = $this->fetcher(new FakeFetcher(), 1.0);

        $fetcher->fetch('https://example.com/a');
        $this->clock += 5.0;
        $fetcher->fetch('https://example.com/b');

        self::assertSame([], $this->sleeps);
    }

    public function testDifferentHostsAreIndependent(): void
    {
        $fetcher = $this->fetcher(new FakeFetcher(), 10.0);

        $fetcher->fetch('https://one.example.com/');
        $fetcher->fetch('https://two.example.com/');

        self::assertSame([], $this->sleeps);
    }
}
