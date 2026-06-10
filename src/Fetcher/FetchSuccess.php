<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Fetcher;

final readonly class FetchSuccess implements FetchOutcome
{
    public function __construct(
        public string $finalUrl,
        public int $status,
        public string $contentType,
        public string $charset,
        public string $body,
    ) {
    }
}
