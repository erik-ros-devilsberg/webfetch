<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Tests\Fetcher;

use Devilsberg\Webfetch\Fetcher\FetchError;
use Devilsberg\Webfetch\Fetcher\FetchFailure;
use Devilsberg\Webfetch\Fetcher\FetchSuccess;
use Devilsberg\Webfetch\Fetcher\RobotsAwareFetcher;
use Devilsberg\Webfetch\Tests\Support\FakeFetcher;
use PHPUnit\Framework\TestCase;

final class RobotsAwareFetcherTest extends TestCase
{
    private const string ROBOTS = <<<'TXT'
        # comment line
        User-agent: somethingelse
        Disallow: /everything

        User-agent: *
        Disallow: /private/
        Disallow: /tmp
        Disallow:
        TXT;

    private int $robotsLoads = 0;

    private function fetcher(FakeFetcher $inner, ?string $robots): RobotsAwareFetcher
    {
        return new RobotsAwareFetcher($inner, loadRobots: function (string $url) use ($robots): ?string {
            ++$this->robotsLoads;

            return $robots;
        });
    }

    public function testDisallowedPathIsRefused(): void
    {
        $inner = new FakeFetcher();
        $fetcher = $this->fetcher($inner, self::ROBOTS);

        $outcome = $fetcher->fetch('https://example.com/private/report.html');

        self::assertInstanceOf(FetchFailure::class, $outcome);
        self::assertSame(FetchError::RobotsDisallowed, $outcome->error);
        self::assertSame(0, $inner->calls());
    }

    public function testAllowedPathPassesThrough(): void
    {
        $inner = new FakeFetcher();
        $fetcher = $this->fetcher($inner, self::ROBOTS);

        $outcome = $fetcher->fetch('https://example.com/public/page.html');

        self::assertInstanceOf(FetchSuccess::class, $outcome);
        self::assertSame(1, $inner->calls());
    }

    public function testOtherAgentsRulesDoNotApply(): void
    {
        $fetcher = $this->fetcher(new FakeFetcher(), self::ROBOTS);

        $outcome = $fetcher->fetch('https://example.com/everything/else');

        self::assertInstanceOf(FetchSuccess::class, $outcome);
    }

    public function testMissingRobotsAllowsEverything(): void
    {
        $fetcher = $this->fetcher(new FakeFetcher(), null);

        $outcome = $fetcher->fetch('https://example.com/private/whatever');

        self::assertInstanceOf(FetchSuccess::class, $outcome);
    }

    public function testRobotsIsLoadedOncePerHost(): void
    {
        $fetcher = $this->fetcher(new FakeFetcher(), self::ROBOTS);

        $fetcher->fetch('https://example.com/a');
        $fetcher->fetch('https://example.com/b');
        $fetcher->fetch('https://other.example.com/c');

        self::assertSame(2, $this->robotsLoads);
    }
}
