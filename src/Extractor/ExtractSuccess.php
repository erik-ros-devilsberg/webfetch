<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Extractor;

final readonly class ExtractSuccess implements ExtractOutcome
{
    public function __construct(
        public PageContent $content,
    ) {
    }
}
