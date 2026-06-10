<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Parser;

final readonly class ParseFailure implements ParseOutcome
{
    public function __construct(
        public ParseError $error,
        public string $message,
    ) {
    }
}
