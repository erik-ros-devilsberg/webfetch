<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Extractor;

/**
 * An extraction always returns one of exactly two shapes: ExtractSuccess
 * or ExtractFailure. Consumers decide with an instanceof check.
 */
interface ExtractOutcome
{
}
