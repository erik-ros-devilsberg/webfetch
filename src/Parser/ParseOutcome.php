<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Parser;

/**
 * A parse always returns one of exactly two shapes: ParseSuccess or
 * ParseFailure. Consumers decide with an instanceof check.
 */
interface ParseOutcome
{
}
