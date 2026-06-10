<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Tests\Cli;

use Devilsberg\Webfetch\Cli\Command;
use Devilsberg\Webfetch\Fetcher\FetchError;
use Devilsberg\Webfetch\Fetcher\Fetcher;
use Devilsberg\Webfetch\Fetcher\FetchFailure;
use Devilsberg\Webfetch\Fetcher\FetchOptions;
use Devilsberg\Webfetch\Fetcher\FetchOutcome;
use Devilsberg\Webfetch\Fetcher\FetchSuccess;
use PHPUnit\Framework\TestCase;

final class CommandTest extends TestCase
{
    /** @var resource */
    private $stdout;

    /** @var resource */
    private $stderr;

    protected function setUp(): void
    {
        $stdout = fopen('php://memory', 'r+');
        $stderr = fopen('php://memory', 'r+');
        self::assertNotFalse($stdout);
        self::assertNotFalse($stderr);
        $this->stdout = $stdout;
        $this->stderr = $stderr;
    }

    /**
     * @param resource $stream
     */
    private static function contents($stream): string
    {
        rewind($stream);

        return (string) stream_get_contents($stream);
    }

    private static function successFetcher(): SpyFetcher
    {
        return new SpyFetcher();
    }

    private static function failingFetcher(): Fetcher
    {
        return new class () implements Fetcher {
            public function fetch(string $url, ?FetchOptions $options = null): FetchOutcome
            {
                return new FetchFailure(FetchError::Timeout, 'simulated timeout');
            }
        };
    }

    private function runCommand(Fetcher $fetcher, string ...$args): int
    {
        $command = new Command($fetcher, new \DateTimeImmutable('2026-06-10T12:00:00+00:00'));

        return $command->run(['webfetch', ...array_values($args)], $this->stdout, $this->stderr);
    }

    public function testSuccessPrintsJsonAndExitsZero(): void
    {
        $exit = $this->runCommand(self::successFetcher(), 'https://example.com/article');

        self::assertSame(0, $exit);
        $out = self::contents($this->stdout);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($out, true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($decoded['ok']);
        self::assertSame('', self::contents($this->stderr));
    }

    public function testFetchErrorPrintsErrorJsonAndExitsOne(): void
    {
        $exit = $this->runCommand(self::failingFetcher(), 'https://example.com/');

        self::assertSame(1, $exit);
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode(self::contents($this->stdout), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($decoded['ok']);
        self::assertSame('timeout', $decoded['error_code']);
    }

    public function testMissingUrlIsUsageError(): void
    {
        $exit = $this->runCommand(self::successFetcher());

        self::assertSame(2, $exit);
        self::assertSame('', self::contents($this->stdout));
        self::assertStringContainsString('Usage:', self::contents($this->stderr));
    }

    public function testUnknownFlagIsUsageError(): void
    {
        $exit = $this->runCommand(self::successFetcher(), '--frobnicate', 'https://example.com/');

        self::assertSame(2, $exit);
        self::assertSame('', self::contents($this->stdout));
        self::assertStringContainsString('frobnicate', self::contents($this->stderr));
    }

    public function testInvalidNumericValueIsUsageError(): void
    {
        $exit = $this->runCommand(self::successFetcher(), '--timeout=banana', 'https://example.com/');

        self::assertSame(2, $exit);
        self::assertStringContainsString('timeout', self::contents($this->stderr));
    }

    public function testHelpPrintsUsageAndExitsZero(): void
    {
        $exit = $this->runCommand(self::successFetcher(), '--help');

        self::assertSame(0, $exit);
        self::assertStringContainsString('Usage:', self::contents($this->stdout));
    }

    public function testFlagsArePassedThroughToFetchOptions(): void
    {
        $fetcher = self::successFetcher();

        $exit = $this->runCommand(
            $fetcher,
            '--timeout=42.5',
            '--connect-timeout=5',
            '--max-redirects=3',
            '--max-bytes=2048',
            '--user-agent=custom-agent/1.0',
            'https://example.com/article',
        );

        self::assertSame(0, $exit);
        $captured = $fetcher->captured;
        self::assertInstanceOf(FetchOptions::class, $captured);
        self::assertSame(42.5, $captured->totalTimeout);
        self::assertSame(5.0, $captured->connectTimeout);
        self::assertSame(3, $captured->maxRedirects);
        self::assertSame(2048, $captured->maxBytes);
        self::assertSame('custom-agent/1.0', $captured->userAgent);
    }
}

/**
 * Records the options it was called with and serves the article fixture.
 */
final class SpyFetcher implements Fetcher
{
    public ?FetchOptions $captured = null;

    public function fetch(string $url, ?FetchOptions $options = null): FetchOutcome
    {
        $this->captured = $options;
        $html = file_get_contents(__DIR__ . '/../fixtures/article.html');

        return new FetchSuccess(
            finalUrl: $url,
            status: 200,
            contentType: 'text/html',
            charset: 'utf-8',
            body: $html === false ? '' : $html,
        );
    }
}
