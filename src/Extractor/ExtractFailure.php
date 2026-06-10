<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Extractor;

final readonly class ExtractFailure implements ExtractOutcome
{
    public function __construct(
        public ExtractError $error,
        public string $message,
    ) {
    }
}
