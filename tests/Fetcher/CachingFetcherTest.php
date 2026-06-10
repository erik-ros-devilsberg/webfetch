<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Tests\Fetcher;

use Devilsberg\Webfetch\Fetcher\CachingFetcher;
use Devilsberg\Webfetch\Fetcher\FetchError;
use Devilsberg\Webfetch\Fetcher\FetchFailure;
use Devilsberg\Webfetch\Fetcher\FetchSuccess;
use Devilsberg\Webfetch\Tests\Support\ArrayCache;
use Devilsberg\Webfetch\Tests\Support\FakeFetcher;
use PHPUnit\Framework\TestCase;

final class CachingFetcherTest extends TestCase
{
    public function testMissFetchesAndStores(): void
    {
        $inner = new FakeFetcher();
        $cache = new ArrayCache();
        $fetcher = new CachingFetcher($inner, $cache, ttlSeconds: 120);

        $outcome = $fetcher->fetch('https://example.com/a');

        self::assertInstanceOf(FetchSuccess::class, $outcome);
        self::assertSame(1, $inner->calls());
        self::assertCount(1, $cache->items);
        self::assertSame(120, array_values($cache->ttls)[0]);
    }

    public function testHitSkipsInnerFetcher(): void
    {
        $inner = new FakeFetcher();
        $fetcher = new CachingFetcher($inner, new ArrayCache());

        $first = $fetcher->fetch('https://example.com/a');
        $second = $fetcher->fetch('https://example.com/a');

        self::assertSame(1, $inner->calls());
        self::assertSame($first, $second);
    }

    public function testDifferentUrlsAreDifferentEntries(): void
    {
        $inner = new FakeFetcher();
        $fetcher = new CachingFetcher($inner, new ArrayCache());

        $fetcher->fetch('https://example.com/a');
        $fetcher->fetch('https://example.com/b');

        self::assertSame(2, $inner->calls());
    }

    public function testFailuresAreNotCached(): void
    {
        $inner = new FakeFetcher();
        $inner->queue(new FetchFailure(FetchError::Timeout, 'slow'));
        $cache = new ArrayCache();
        $fetcher = new CachingFetcher($inner, $cache);

        $first = $fetcher->fetch('https://example.com/flaky');
        $second = $fetcher->fetch('https://example.com/flaky');

        self::assertInstanceOf(FetchFailure::class, $first);
        self::assertInstanceOf(FetchSuccess::class, $second);
        self::assertSame(2, $inner->calls());
    }
}
