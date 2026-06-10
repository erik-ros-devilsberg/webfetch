<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Tests\Fetcher;

use Devilsberg\Webfetch\Fetcher\FetchError;
use Devilsberg\Webfetch\Fetcher\FetchFailure;
use Devilsberg\Webfetch\Fetcher\FetchOptions;
use Devilsberg\Webfetch\Fetcher\FetchSuccess;
use Devilsberg\Webfetch\Fetcher\StaticFetcher;
use Devilsberg\Webfetch\Fetcher\UrlGuard;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UrlGuardTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function blockedUrls(): iterable
    {
        yield 'loopback v4' => ['http://127.0.0.1/admin'];
        yield 'loopback v4 high' => ['http://127.0.0.53/x'];
        yield 'private 10.x' => ['http://10.0.0.5/'];
        yield 'private 172.16' => ['http://172.16.3.4/'];
        yield 'private 192.168' => ['http://192.168.1.1/router'];
        yield 'link-local' => ['http://169.254.169.254/latest/meta-data/'];
        yield 'loopback v6' => ['http://[::1]/'];
        yield 'localhost' => ['http://localhost/'];
        yield 'localhost subdomain' => ['http://foo.localhost/'];
    }

    #[DataProvider('blockedUrls')]
    public function testPrivateTargetsAreBlocked(string $url): void
    {
        self::assertNotNull(UrlGuard::check($url));
    }

    public function testPublicHostsAreAllowed(): void
    {
        self::assertNull(UrlGuard::check('https://example.com/page'));
        self::assertNull(UrlGuard::check('http://93.184.216.34/'));
    }

    public function testStaticFetcherRefusesBlockedUrlWithoutNetwork(): void
    {
        // Empty mock queue: any actual HTTP attempt would throw.
        $fetcher = new StaticFetcher(new Client(['handler' => HandlerStack::create(new MockHandler([]))]));

        $outcome = $fetcher->fetch('http://169.254.169.254/latest/meta-data/');

        self::assertInstanceOf(FetchFailure::class, $outcome);
        self::assertSame(FetchError::BlockedUrl, $outcome->error);
    }

    public function testAllowPrivateTargetsBypassesGuard(): void
    {
        $fetcher = new StaticFetcher(new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(200, ['Content-Type' => 'text/html'], '<html><body>internal</body></html>'),
        ]))]));

        $outcome = $fetcher->fetch('http://127.0.0.1/status', new FetchOptions(allowPrivateTargets: true));

        self::assertInstanceOf(FetchSuccess::class, $outcome);
    }

    public function testRedirectToPrivateTargetIsBlocked(): void
    {
        $fetcher = new StaticFetcher(new Client(['handler' => HandlerStack::create(new MockHandler([
            new Response(301, ['Location' => 'http://127.0.0.1/internal']),
            new Response(200, ['Content-Type' => 'text/html'], 'should never be reached'),
        ]))]));

        $outcome = $fetcher->fetch('https://example.com/innocent');

        self::assertInstanceOf(FetchFailure::class, $outcome);
        self::assertSame(FetchError::BlockedUrl, $outcome->error);
    }
}
