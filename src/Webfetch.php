<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch;

use Devilsberg\Webfetch\Extractor\ExtractFailure;
use Devilsberg\Webfetch\Extractor\Extractor;
use Devilsberg\Webfetch\Extractor\ExtractSuccess;
use Devilsberg\Webfetch\Fetcher\Fetcher;
use Devilsberg\Webfetch\Fetcher\FetchFailure;
use Devilsberg\Webfetch\Fetcher\FetchOptions;
use Devilsberg\Webfetch\Fetcher\FetchSuccess;
use Devilsberg\Webfetch\Fetcher\StaticFetcher;
use Devilsberg\Webfetch\Serializer\JsonSerializer;

/**
 * The public entry point: URL in, JSON string out — always. Pipeline
 * failures (network, HTTP, parse, extraction) come back as error JSON
 * (schema/webfetch-error.schema.json), never as exceptions.
 */
final class Webfetch
{
    public const string VERSION = '1.0.0';

    private function __construct(
        private readonly Fetcher $fetcher,
        private readonly Extractor $extractor,
        private readonly JsonSerializer $serializer,
        private readonly ?\DateTimeImmutable $fetchedAt,
    ) {
    }

    /**
     * @param Fetcher|null            $fetcher   custom fetcher (e.g. a future ChromeFetcher, or a mocked one in tests)
     * @param \DateTimeImmutable|null $fetchedAt fixed timestamp for reproducible output; defaults to now
     */
    public static function create(?Fetcher $fetcher = null, ?\DateTimeImmutable $fetchedAt = null): self
    {
        return new self(
            $fetcher ?? new StaticFetcher(),
            new Extractor(),
            new JsonSerializer(),
            $fetchedAt,
        );
    }

    public function fetch(string $url, ?FetchOptions $options = null): string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (filter_var($url, FILTER_VALIDATE_URL) === false || !in_array($scheme, ['http', 'https'], true)) {
            return $this->serializer->error(ErrorCode::InvalidUrl, "Not a valid http(s) URL: {$url}", $url, null);
        }

        $fetched = $this->fetcher->fetch($url, $options);
        if ($fetched instanceof FetchFailure) {
            return $this->serializer->error(
                ErrorCode::fromFetchError($fetched->error),
                $fetched->message,
                $url,
                $fetched->httpStatus,
            );
        }
        if (!$fetched instanceof FetchSuccess) {
            return $this->serializer->error(ErrorCode::ConnectionFailed, 'Unknown fetch outcome', $url, null);
        }

        $extracted = $this->extractor->extract($fetched);
        if ($extracted instanceof ExtractFailure) {
            return $this->serializer->error(
                ErrorCode::fromExtractError($extracted->error),
                $extracted->message,
                $fetched->finalUrl,
                null,
            );
        }
        if (!$extracted instanceof ExtractSuccess) {
            return $this->serializer->error(ErrorCode::EmptyExtraction, 'Unknown extract outcome', $fetched->finalUrl, null);
        }

        return $this->serializer->success(
            $extracted->content,
            $fetched->finalUrl,
            $this->fetchedAt ?? new \DateTimeImmutable(),
        );
    }
}
