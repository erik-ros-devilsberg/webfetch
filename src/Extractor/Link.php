<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Extractor;

final readonly class Link
{
    public function __construct(
        public string $text,
        public string $href,
    ) {
    }
}
