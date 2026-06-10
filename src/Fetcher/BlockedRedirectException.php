<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Fetcher;

use GuzzleHttp\Exception\GuzzleException;

/**
 * Internal: thrown from the redirect callback when a redirect points at a
 * blocked target; caught inside StaticFetcher and turned into a
 * blocked_url failure. Implements GuzzleException because it surfaces
 * through Guzzle's request call.
 */
final class BlockedRedirectException extends \RuntimeException implements GuzzleException
{
}
