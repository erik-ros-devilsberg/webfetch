<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Fetcher;

final readonly class FetchOptions
{
    public function __construct(
        public float $connectTimeout = 10.0,
        public float $totalTimeout = 30.0,
        public int $maxRedirects = 5,
        public int $maxBytes = 10_000_000,
        public string $userAgent = 'devilsberg-webfetch/0.1 (+https://packagist.org/packages/devilsberg/webfetch)',
        public bool $allowPrivateTargets = false,
    ) {
    }
}
