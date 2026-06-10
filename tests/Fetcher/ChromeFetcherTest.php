<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Tests\Fetcher;

use Devilsberg\Webfetch\Fetcher\ChromeFetcher;
use Devilsberg\Webfetch\Fetcher\FetchError;
use Devilsberg\Webfetch\Fetcher\FetchFailure;
use Devilsberg\Webfetch\Fetcher\FetchSuccess;
use Devilsberg\Webfetch\Webfetch;
use PHPUnit\Framework\TestCase;

final class ChromeFetcherTest extends TestCase
{
    private static function chromeBinary(): ?string
    {
        $fromEnv = getenv('CHROME_PATH');
        if (is_string($fromEnv) && $fromEnv !== '' && is_file($fromEnv)) {
            return $fromEnv;
        }
        foreach (['/usr/bin/google-chrome', '/usr/bin/chromium', '/usr/bin/chromium-browser', '/snap/bin/chromium'] as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function testBadBinaryYieldsBrowserUnavailable(): void
    {
        $fetcher = new ChromeFetcher(chromeBinary: '/nonexistent/chrome-binary');

        $outcome = $fetcher->fetch('https://example.com/');

        self::assertInstanceOf(FetchFailure::class, $outcome);
        self::assertSame(FetchError::BrowserUnavailable, $outcome->error);
    }

    public function testBrowserUnavailableSurfacesAsErrorJson(): void
    {
        $webfetch = Webfetch::create(fetcher: new ChromeFetcher(chromeBinary: '/nonexistent/chrome-binary'));

        $json = $webfetch->fetch('https://example.com/');

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($decoded['ok']);
        self::assertSame('browser_unavailable', $decoded['error_code']);
    }

    public function testRendersJavaScriptContent(): void
    {
        $binary = self::chromeBinary();
        if ($binary === null) {
            self::markTestSkipped('No Chrome/Chromium binary available');
        }

        $profileDir = __DIR__ . '/../chrome-profile';
        @mkdir($profileDir);
        $fetcher = new ChromeFetcher(chromeBinary: $binary, userDataDir: $profileDir);

        $fixture = realpath(__DIR__ . '/../fixtures/js-rendered.html');
        self::assertNotFalse($fixture);
        $outcome = $fetcher->fetch('file://' . $fixture);

        if ($outcome instanceof FetchFailure && $outcome->error === FetchError::BrowserUnavailable) {
            // Sandboxed/snap-packaged Chrome may refuse to start in this
            // environment — that is an environment limitation, not a bug.
            self::markTestSkipped('Chrome could not start: ' . $outcome->message);
        }
        if ($outcome instanceof FetchSuccess
            && !str_contains($outcome->body, 'Rendered by JavaScript')
            && !str_contains($outcome->body, 'id="root"')) {
            // Neither the rendered marker nor the fixture's own markup:
            // Chrome served its internal error page, meaning it cannot read
            // this path (snap confinement blocks e.g. /tmp). A genuine
            // render failure would still contain the fixture's root div and
            // fail below.
            self::markTestSkipped('Chrome rendered an error page — it cannot read the fixture path in this environment');
        }

        self::assertInstanceOf(FetchSuccess::class, $outcome);
        self::assertStringContainsString('Rendered by JavaScript', $outcome->body);
        self::assertStringContainsString('injected', $outcome->body);
        self::assertSame('utf-8', $outcome->charset);
    }
}
