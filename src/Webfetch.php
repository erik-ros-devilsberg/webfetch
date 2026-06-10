<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch;

/**
 * Package identity. The real entry point (fetch a URL, get JSON) arrives
 * with the Fetcher and extraction stories — see docs/roadmap.md.
 */
final class Webfetch
{
    public const string VERSION = '0.1.0-dev';

    private function __construct()
    {
    }
}
