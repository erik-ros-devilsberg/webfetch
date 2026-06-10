<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Fetcher;

/**
 * A fetch always returns one of exactly two shapes: FetchSuccess or
 * FetchFailure. Consumers decide with an instanceof check.
 */
interface FetchOutcome
{
}
