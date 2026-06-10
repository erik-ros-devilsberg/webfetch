<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Parser;

/**
 * Machine-readable parse failure reasons — part of the story 06 error
 * JSON contract; treat renames as breaking changes.
 */
enum ParseError: string
{
    case EmptyBody = 'empty_body';
    case ParseFailed = 'parse_failure';
}
