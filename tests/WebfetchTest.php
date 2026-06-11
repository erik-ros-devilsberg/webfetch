<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Tests;

use Devilsberg\Webfetch\ErrorCode;
use Devilsberg\Webfetch\Fetcher\FetchOptions;
use Devilsberg\Webfetch\Fetcher\StaticFetcher;
use Devilsberg\Webfetch\Serializer\JsonSerializer;
use Devilsberg\Webfetch\Webfetch;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;

final class WebfetchTest extends TestCase
{
    /**
     * @param list<Response|ConnectException> $queue
     */
    private static function webfetch(array $queue): Webfetch
    {
        $handler = HandlerStack::create(new MockHandler($queue));
        $fetcher = new StaticFetcher(new Client(['handler' => $handler]));

        return Webfetch::create(fetcher: $fetcher, fetchedAt: new \DateTimeImmutable('2026-06-10T12:00:00+00:00'));
    }

    private static function fixture(string $name): string
    {
        $html = file_get_contents(__DIR__ . '/fixtures/' . $name);
        self::assertNotFalse($html);

        return $html;
    }

    private static function assertMatchesSchema(string $json, string $schemaFile): void
    {
        $schemaJson = file_get_contents(__DIR__ . '/../schema/' . $schemaFile);
        self::assertNotFalse($schemaJson);
        $schema = json_decode($schemaJson);
        self::assertIsObject($schema);

        $result = new Validator()->validate(json_decode($json), $schema);
        self::assertTrue($result->isValid(), "Output does not match {$schemaFile}: {$json}");
    }

    /**
     * @return array<string, mixed>
     */
    private static function assertErrorJson(string $json, ErrorCode $expected): array
    {
        self::assertMatchesSchema($json, 'webfetch-error.schema.json');
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($decoded['ok']);
        self::assertSame($expected->value, $decoded['error_code']);

        return $decoded;
    }

    public function testInvalidUrlReturnsErrorJson(): void
    {
        $json = self::webfetch([])->fetch('definitely not a url');

        self::assertErrorJson($json, ErrorCode::InvalidUrl);
    }

    public function testNonHttpSchemeReturnsInvalidUrl(): void
    {
        $json = self::webfetch([])->fetch('ftp://example.com/file.html');

        self::assertErrorJson($json, ErrorCode::InvalidUrl);
    }

    public function testConnectionFailedReturnsErrorJson(): void
    {
        $request = new Request('GET', 'https://nope.invalid/');
        $json = self::webfetch([
            new ConnectException('cURL error 6: Could not resolve host', $request, null, ['errno' => 6]),
        ])->fetch('https://nope.invalid/');

        self::assertErrorJson($json, ErrorCode::ConnectionFailed);
    }

    public function testTimeoutReturnsErrorJson(): void
    {
        $request = new Request('GET', 'https://slow.example.com/');
        $json = self::webfetch([
            new ConnectException('cURL error 28: Operation timed out', $request, null, ['errno' => 28]),
        ])->fetch('https://slow.example.com/');

        self::assertErrorJson($json, ErrorCode::Timeout);
    }

    public function testTooManyRedirectsReturnsErrorJson(): void
    {
        $json = self::webfetch([
            new Response(301, ['Location' => 'https://example.com/1']),
            new Response(301, ['Location' => 'https://example.com/2']),
            new Response(301, ['Location' => 'https://example.com/3']),
        ])->fetch('https://example.com/', new FetchOptions(maxRedirects: 2));

        self::assertErrorJson($json, ErrorCode::TooManyRedirects);
    }

    public function testResponseTooLargeReturnsErrorJson(): void
    {
        $json = self::webfetch([
            new Response(200, ['Content-Type' => 'text/html'], str_repeat('x', 2048)),
        ])->fetch('https://example.com/', new FetchOptions(maxBytes: 1024));

        self::assertErrorJson($json, ErrorCode::ResponseTooLarge);
    }

    public function testHttpClientErrorCarriesStatus(): void
    {
        $json = self::webfetch([
            new Response(404, ['Content-Type' => 'text/html'], 'gone'),
        ])->fetch('https://example.com/missing');

        $decoded = self::assertErrorJson($json, ErrorCode::HttpClientError);
        self::assertSame(404, $decoded['http_status']);
    }

    public function testHttpServerErrorCarriesStatus(): void
    {
        $json = self::webfetch([
            new Response(503, ['Content-Type' => 'text/html'], 'down'),
        ])->fetch('https://example.com/');

        $decoded = self::assertErrorJson($json, ErrorCode::HttpServerError);
        self::assertSame(503, $decoded['http_status']);
    }

    public function testNotHtmlReturnsErrorJson(): void
    {
        $json = self::webfetch([
            new Response(200, ['Content-Type' => 'application/json'], '{}'),
        ])->fetch('https://api.example.com/');

        self::assertErrorJson($json, ErrorCode::NotHtml);
    }

    public function testEmptyBodyReturnsErrorJson(): void
    {
        $json = self::webfetch([
            new Response(200, ['Content-Type' => 'text/html'], '   '),
        ])->fetch('https://example.com/blank');

        self::assertErrorJson($json, ErrorCode::EmptyBody);
    }

    public function testEmptyExtractionReturnsErrorJson(): void
    {
        $json = self::webfetch([
            new Response(200, ['Content-Type' => 'text/html'], self::fixture('empty-shell.html')),
        ])->fetch('https://spa.example.com/');

        $decoded = self::assertErrorJson($json, ErrorCode::EmptyExtraction);
        self::assertNull($decoded['http_status']);
    }

    public function testParseFailureErrorJsonValidates(): void
    {
        // lexbor parses anything non-empty, so this code is near-unreachable
        // through the pipeline — pin the serialized shape directly instead.
        $json = new JsonSerializer()->error(
            ErrorCode::ParseFailure,
            'Simulated parser explosion',
            'https://example.com/',
            null,
        );

        self::assertErrorJson($json, ErrorCode::ParseFailure);
    }

    public function testBlockedUrlReturnsErrorJson(): void
    {
        // No mock responses queued: the SSRF guard must refuse before any I/O.
        $json = self::webfetch([])->fetch('http://169.254.169.254/latest/meta-data/');

        self::assertErrorJson($json, ErrorCode::BlockedUrl);
    }

    public function testRobotsDisallowedReturnsErrorJson(): void
    {
        $handler = HandlerStack::create(new MockHandler([]));
        $fetcher = new \Devilsberg\Webfetch\Fetcher\RobotsAwareFetcher(
            new StaticFetcher(new Client(['handler' => $handler])),
            loadRobots: static fn (string $url): string => "User-agent: *\nDisallow: /private/",
        );

        $json = Webfetch::create(fetcher: $fetcher)->fetch('https://example.com/private/x');

        self::assertErrorJson($json, ErrorCode::RobotsDisallowed);
    }

    public function testSuccessEndToEndThroughFacade(): void
    {
        $json = self::webfetch([
            new Response(200, ['Content-Type' => 'text/html; charset=UTF-8'], self::fixture('article.html')),
        ])->fetch('https://example.com/article');

        self::assertMatchesSchema($json, 'webfetch-success.schema.json');
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($decoded['ok']);
        self::assertSame('https://example.com/article', $decoded['url']);
        self::assertSame('2026-06-10T12:00:00+00:00', $decoded['fetched_at']);
        self::assertSame('html', $decoded['source_type']);
    }
}
