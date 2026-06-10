<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Fetcher;

use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\TooManyRedirectsException;
use GuzzleHttp\TransferStats;

/**
 * Plain HTTP fetcher for server-rendered pages. JavaScript-rendered pages
 * come back as empty shells here — that is the ChromeFetcher's job (story 09).
 */
final class StaticFetcher implements Fetcher
{
    private const int READ_CHUNK_BYTES = 8192;

    /** cURL errno for any timeout (connect or transfer). */
    private const int CURLE_OPERATION_TIMEDOUT = 28;

    private readonly ClientInterface $client;

    public function __construct(?ClientInterface $client = null)
    {
        $this->client = $client ?? new Client();
    }

    public function fetch(string $url, ?FetchOptions $options = null): FetchOutcome
    {
        $options ??= new FetchOptions();
        $finalUrl = $url;

        if (!$options->allowPrivateTargets && ($reason = UrlGuard::check($url)) !== null) {
            return new FetchFailure(FetchError::BlockedUrl, $reason);
        }

        try {
            $response = $this->client->request('GET', $url, [
                'http_errors' => false,
                'stream' => true,
                'allow_redirects' => [
                    'max' => $options->maxRedirects,
                    // Every redirect hop gets the same SSRF treatment as the
                    // initial URL — a public page must not bounce us inside.
                    'on_redirect' => function ($request, $response, $uri) use ($options): void {
                        if (!$options->allowPrivateTargets && ($reason = UrlGuard::check((string) $uri)) !== null) {
                            throw new BlockedRedirectException("Redirect blocked: {$reason}");
                        }
                    },
                ],
                'connect_timeout' => $options->connectTimeout,
                'timeout' => $options->totalTimeout,
                'headers' => [
                    'User-Agent' => $options->userAgent,
                    'Accept' => 'text/html,application/xhtml+xml;q=0.9,*/*;q=0.1',
                ],
                // Each redirect hop calls this; the last call holds the final URL.
                'on_stats' => function (TransferStats $stats) use (&$finalUrl): void {
                    $finalUrl = (string) $stats->getEffectiveUri();
                },
            ]);
        } catch (BlockedRedirectException $e) {
            return new FetchFailure(FetchError::BlockedUrl, $e->getMessage());
        } catch (TooManyRedirectsException $e) {
            return new FetchFailure(FetchError::TooManyRedirects, $e->getMessage());
        } catch (ConnectException $e) {
            $errno = $e->getHandlerContext()['errno'] ?? null;
            $error = $errno === self::CURLE_OPERATION_TIMEDOUT ? FetchError::Timeout : FetchError::ConnectionFailed;

            return new FetchFailure($error, $e->getMessage());
        } catch (GuzzleException $e) {
            return new FetchFailure(FetchError::ConnectionFailed, $e->getMessage());
        }

        $status = $response->getStatusCode();
        if ($status >= 400) {
            $error = $status >= 500 ? FetchError::HttpServerError : FetchError::HttpClientError;

            return new FetchFailure($error, "HTTP {$status} for {$finalUrl}", httpStatus: $status);
        }

        $contentTypeHeader = $response->getHeaderLine('Content-Type');
        $contentType = strtolower(trim(explode(';', $contentTypeHeader)[0]));
        if ($contentType !== '' && !self::isHtml($contentType)) {
            return new FetchFailure(
                FetchError::NotHtml,
                "Content type '{$contentType}' is not HTML",
                httpStatus: $status,
                contentType: $contentType,
            );
        }

        // Stream the body in chunks so the size cap holds even when the
        // Content-Length header is absent or lying.
        $body = '';
        $stream = $response->getBody();
        while (!$stream->eof()) {
            $body .= $stream->read(self::READ_CHUNK_BYTES);
            if (strlen($body) > $options->maxBytes) {
                $stream->close();

                return new FetchFailure(
                    FetchError::ResponseTooLarge,
                    "Response exceeded {$options->maxBytes} bytes",
                    httpStatus: $status,
                );
            }
        }

        return new FetchSuccess(
            finalUrl: $finalUrl,
            status: $status,
            contentType: $contentType === '' ? 'text/html' : $contentType,
            charset: self::detectCharset($contentTypeHeader, $body),
            body: $body,
        );
    }

    private static function isHtml(string $contentType): bool
    {
        return $contentType === 'text/html' || $contentType === 'application/xhtml+xml';
    }

    private static function detectCharset(string $contentTypeHeader, string $body): string
    {
        if (preg_match('/charset=["\']?\s*([a-z0-9_.:-]+)/i', $contentTypeHeader, $m) === 1) {
            return strtolower($m[1]);
        }

        // No header charset — scan the document head for a meta declaration.
        $head = substr($body, 0, 4096);
        if (preg_match('/<meta\s+charset=["\']?\s*([a-z0-9_.:-]+)/i', $head, $m) === 1) {
            return strtolower($m[1]);
        }
        if (preg_match('/<meta[^>]+content=["\'][^"\']*charset=([a-z0-9_.:-]+)/i', $head, $m) === 1) {
            return strtolower($m[1]);
        }

        return 'utf-8';
    }
}
