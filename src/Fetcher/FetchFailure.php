<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Fetcher;

final readonly class FetchFailure implements FetchOutcome
{
    public function __construct(
        public FetchError $error,
        public string $message,
        public ?int $httpStatus = null,
        public ?string $contentType = null,
    ) {
    }
}
