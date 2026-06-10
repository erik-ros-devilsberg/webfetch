<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Extractor;

/**
 * Machine-readable extraction failure reasons — part of the story 06
 * error JSON contract; treat renames as breaking changes.
 */
enum ExtractError: string
{
    case EmptyExtraction = 'empty_extraction';

    /** Pass-throughs from the parse stage so the pipeline keeps the cause. */
    case EmptyBody = 'empty_body';
    case ParseFailed = 'parse_failure';
}
