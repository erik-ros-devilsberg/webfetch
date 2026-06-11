<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Extractor;

/**
 * How the content was obtained. Part of the public JSON contract.
 */
enum ExtractionStrategy: string
{
    case Readability = 'readability';
    case Fallback = 'fallback';

    /** PDF text + metadata extraction (the only path for PDF sources). */
    case Pdf = 'pdf';
}
