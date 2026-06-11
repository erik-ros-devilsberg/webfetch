<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Extractor;

/**
 * Which source format the content was extracted from. Part of the public
 * JSON contract (the `source_type` field); treat renames as breaking
 * changes. `pdf` and other document formats join this as their extractors
 * land.
 */
enum SourceType: string
{
    case Html = 'html';
    case Pdf = 'pdf';
}
