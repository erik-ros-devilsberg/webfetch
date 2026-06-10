<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Fetcher;

use HeadlessChromium\Browser;
use HeadlessChromium\BrowserFactory;
use HeadlessChromium\Exception\OperationTimedOut;

/**
 * Fetches pages through headless Chrome so JavaScript-rendered content
 * (SPAs, consent shells) becomes extractable. Same Fetcher contract as
 * StaticFetcher — use static-first and fall back to this on
 * empty_extraction.
 *
 * chrome-php/chrome is an OPTIONAL dependency (composer suggest); when it
 * is not installed, or Chrome cannot start, fetch() returns a
 * browser_unavailable failure instead of throwing.
 *
 * Known limitation: the HTTP status of a browser navigation is not
 * reliably observable through chrome-php's high-level API, so a
 * successfully rendered page reports status 200.
 */
final readonly class ChromeFetcher implements Fetcher
{
    public const string WAIT_LOAD = 'load';
    public const string WAIT_NETWORK_IDLE = 'networkIdle';

    /**
     * @param string|null $chromeBinary path to Chrome/Chromium; null lets chrome-php auto-discover
     * @param string      $waitEvent    WAIT_LOAD or WAIT_NETWORK_IDLE
     * @param float       $renderDelay  extra seconds to wait after the wait event, for late-rendering apps
     * @param string|null $userDataDir  Chrome profile directory (snap-packaged Chrome may need one under $HOME)
     * @param bool        $noSandbox    disable Chrome's sandbox — required in many container/CI environments
     */
    public function __construct(
        private ?string $chromeBinary = null,
        private string $waitEvent = self::WAIT_LOAD,
        private float $renderDelay = 0.0,
        private ?string $userDataDir = null,
        private bool $noSandbox = true,
    ) {
    }

    public function fetch(string $url, ?FetchOptions $options = null): FetchOutcome
    {
        if (!class_exists(BrowserFactory::class)) {
            return new FetchFailure(
                FetchError::BrowserUnavailable,
                'chrome-php/chrome is not installed — run: composer require chrome-php/chrome',
            );
        }

        $options ??= new FetchOptions();
        $timeoutMs = (int) ($options->totalTimeout * 1000);

        try {
            $browserOptions = [
                'headless' => true,
                'noSandbox' => $this->noSandbox,
                'userAgent' => $options->userAgent,
                'sendSyncDefaultTimeout' => $timeoutMs,
            ];
            if ($this->userDataDir !== null) {
                $browserOptions['userDataDir'] = $this->userDataDir;
            }
            $browser = new BrowserFactory($this->chromeBinary)->createBrowser($browserOptions);
        } catch (\Throwable $e) {
            return new FetchFailure(FetchError::BrowserUnavailable, 'Chrome could not start: ' . $e->getMessage());
        }

        try {
            return $this->renderPage($browser, $url, $options, $timeoutMs);
        } finally {
            try {
                $browser->close();
            } catch (\Throwable) {
                // A browser that refuses to close cleanly is not the caller's problem.
            }
        }
    }

    private function renderPage(Browser $browser, string $url, FetchOptions $options, int $timeoutMs): FetchOutcome
    {
        try {
            $page = $browser->createPage();
            $page->navigate($url)->waitForNavigation($this->waitEvent, $timeoutMs);
            if ($this->renderDelay > 0) {
                usleep((int) ($this->renderDelay * 1_000_000));
            }
            $html = $page->getHtml($timeoutMs);
            $finalUrl = $page->getCurrentUrl();
        } catch (OperationTimedOut $e) {
            return new FetchFailure(FetchError::Timeout, $e->getMessage());
        } catch (\Throwable $e) {
            return new FetchFailure(FetchError::ConnectionFailed, $e->getMessage());
        }

        if (strlen($html) > $options->maxBytes) {
            return new FetchFailure(
                FetchError::ResponseTooLarge,
                "Rendered document exceeded {$options->maxBytes} bytes",
            );
        }

        // Chrome hands back the rendered DOM serialized as UTF-8.
        return new FetchSuccess(
            finalUrl: $finalUrl,
            status: 200,
            contentType: 'text/html',
            charset: 'utf-8',
            body: $html,
        );
    }
}
