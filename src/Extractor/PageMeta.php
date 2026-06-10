<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Extractor;

/**
 * The handful of page-level metadata fields worth keeping, harvested from
 * og:* and standard meta tags.
 */
final readonly class PageMeta
{
    public function __construct(
        public ?string $siteName = null,
        public ?string $description = null,
        public ?string $image = null,
        public ?string $type = null,
    ) {
    }
}
