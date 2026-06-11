<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Tests\Fetcher;

use Devilsberg\Webfetch\Fetcher\FetchError;
use Devilsberg\Webfetch\Fetcher\FetchFailure;
use Devilsberg\Webfetch\Fetcher\FetchOptions;
use Devilsberg\Webfetch\Fetcher\FetchSuccess;
use Devilsberg\Webfetch\Fetcher\StaticFetcher;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class StaticFetcherTest extends TestCase
{
    /**
     * @param list<Response|ConnectException> $queue
     */
    private static function fetcher(array $queue): StaticFetcher
    {
        $handler = HandlerStack::create(new MockHandler($queue));

        return new StaticFetcher(new Client(['handler' => $handler]));
    }

    public function testSuccessfulFetchReturnsSuccess(): void
    {
        $fetcher = self::fetcher([
            new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], '<html><body>Hello</body></html>'),
        ]);

        $outcome = $fetcher->fetch('https://example.com/page');

        self::assertInstanceOf(FetchSuccess::class, $outcome);
        self::assertSame(200, $outcome->status);
        self::assertSame('https://example.com/page', $outcome->finalUrl);
        self::assertSame('text/html', $outcome->contentType);
        self::assertSame('utf-8', $outcome->charset);
        self::assertSame('<html><body>Hello</body></html>', $outcome->body);
    }

    public function testRedirectIsFollowedAndFinalUrlReported(): void
    {
        $fetcher = self::fetcher([
            new Response(301, ['Location' => 'https://example.com/moved']),
            new Response(200, ['Content-Type' => 'text/html'], '<html></html>'),
        ]);

        $outcome = $fetcher->fetch('https://example.com/old');

        self::assertInstanceOf(FetchSuccess::class, $outcome);
        self::assertSame('https://example.com/moved', $outcome->finalUrl);
    }

    public function testTooManyRedirectsIsTypedFailure(): void
    {
        $fetcher = self::fetcher([
            new Response(301, ['Location' => 'https://example.com/1']),
            new Response(301, ['Location' => 'https://example.com/2']),
            new Response(301, ['Location' => 'https://example.com/3']),
        ]);

        $outcome = $fetcher->fetch('https://example.com/', new FetchOptions(maxRedirects: 2));

        self::assertInstanceOf(FetchFailure::class, $outcome);
        self::assertSame(FetchError::TooManyRedirects, $outcome->error);
    }

    public function testTimeoutIsTypedFailure(): void
    {
        $request = new Request('GET', 'https://example.com/');
        $fetcher = self::fetcher([
            new ConnectException('cURL error 28: Operation timed out', $request, null, ['errno' => 28]),
        ]);

        $outcome = $fetcher->fetch('https://example.com/');

        self::assertInstanceOf(FetchFailure::class, $outcome);
        self::assertSame(FetchError::Timeout, $outcome->error);
    }

    public function testConnectionErrorIsTypedFailure(): void
    {
        $request = new Request('GET', 'https://nope.invalid/');
        $fetcher = self::fetcher([
            new ConnectException('cURL error 6: Could not resolve host', $request, null, ['errno' => 6]),
        ]);

        $outcome = $fetcher->fetch('https://nope.invalid/');

        self::assertInstanceOf(FetchFailure::class, $outcome);
        self::assertSame(FetchError::ConnectionFailed, $outcome->error);
    }

    public function testOversizeResponseIsTypedFailure(): void
    {
        $fetcher = self::fetcher([
            new Response(200, ['Content-Type' => 'text/html'], str_repeat('x', 2048)),
        ]);

        $outcome = $fetcher->fetch('https://example.com/', new FetchOptions(maxBytes: 1024));

        self::assertInstanceOf(FetchFailure::class, $outcome);
        self::assertSame(FetchError::ResponseTooLarge, $outcome->error);
    }

    public function testClientErrorStatusIsTypedFailure(): void
    {
        $fetcher = self::fetcher([
            new Response(404, ['Content-Type' => 'text/html'], 'not found'),
        ]);

        $outcome = $fetcher->fetch('https://example.com/missing');

        self::assertInstanceOf(FetchFailure::class, $outcome);
        self::assertSame(FetchError::HttpClientError, $outcome->error);
        self::assertSame(404, $outcome->httpStatus);
    }

    public function testServerErrorStatusIsTypedFailure(): void
    {
        $fetcher = self::fetcher([
            new Response(500, ['Content-Type' => 'text/html'], 'boom'),
        ]);

        $outcome = $fetcher->fetch('https://example.com/broken');

        self::assertInstanceOf(FetchFailure::class, $outcome);
        self::assertSame(FetchError::HttpServerError, $outcome->error);
        self::assertSame(500, $outcome->httpStatus);
    }

    public function testCharsetIsDetectedFromContentTypeHeader(): void
    {
        $fetcher = self::fetcher([
            new Response(200, ['Content-Type' => 'text/html; charset=ISO-8859-1'], '<html></html>'),
        ]);

        $outcome = $fetcher->fetch('https://example.com/');

        self::assertInstanceOf(FetchSuccess::class, $outcome);
        self::assertSame('iso-8859-1', $outcome->charset);
    }

    public function testCharsetFallsBackToMetaTag(): void
    {
        $fetcher = self::fetcher([
            new Response(200, ['Content-Type' => 'text/html'], '<html><head><meta charset="windows-1252"></head></html>'),
        ]);

        $outcome = $fetcher->fetch('https://example.com/');

        self::assertInstanceOf(FetchSuccess::class, $outcome);
        self::assertSame('windows-1252', $outcome->charset);
    }

    public function testCharsetDefaultsToUtf8(): void
    {
        $fetcher = self::fetcher([
            new Response(200, ['Content-Type' => 'text/html'], '<html></html>'),
        ]);

        $outcome = $fetcher->fetch('https://example.com/');

        self::assertInstanceOf(FetchSuccess::class, $outcome);
        self::assertSame('utf-8', $outcome->charset);
    }

    public function testNonHtmlContentTypeIsTypedFailure(): void
    {
        $fetcher = self::fetcher([
            new Response(200, ['Content-Type' => 'application/json'], '{"not": "html"}'),
        ]);

        $outcome = $fetcher->fetch('https://api.example.com/data');

        self::assertInstanceOf(FetchFailure::class, $outcome);
        self::assertSame(FetchError::NotHtml, $outcome->error);
        self::assertSame('application/json', $outcome->contentType);
    }

    public function testPdfContentTypeIsAccepted(): void
    {
        $fetcher = self::fetcher([
            new Response(200, ['Content-Type' => 'application/pdf'], '%PDF-1.4 ...'),
        ]);

        $outcome = $fetcher->fetch('https://example.com/doc.pdf');

        self::assertInstanceOf(FetchSuccess::class, $outcome);
        self::assertSame('application/pdf', $outcome->contentType);
    }
}
