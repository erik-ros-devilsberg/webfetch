<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Parser;

final readonly class ParseSuccess implements ParseOutcome
{
    /**
     * @param string $utf8Body the body after charset normalization — what
     *                         the document was actually parsed from
     */
    public function __construct(
        public \Dom\HTMLDocument $document,
        public string $utf8Body,
    ) {
    }
}
