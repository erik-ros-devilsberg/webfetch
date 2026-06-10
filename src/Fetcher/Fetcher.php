<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Fetcher;

/**
 * The seam between "get me this page" and how it is gotten. The static
 * HTTP fetcher implements this; a headless-Chrome fetcher can implement
 * it later without extraction code noticing.
 */
interface Fetcher
{
    public function fetch(string $url, ?FetchOptions $options = null): FetchOutcome;
}
