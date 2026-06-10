<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Parser;

final readonly class ParseSuccess implements ParseOutcome
{
    public function __construct(
        public \Dom\HTMLDocument $document,
    ) {
    }
}
