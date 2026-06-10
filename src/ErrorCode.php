<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch;

use Devilsberg\Webfetch\Extractor\ExtractError;
use Devilsberg\Webfetch\Fetcher\FetchError;

/**
 * The public error codes — every way a webfetch can fail, unified across
 * the fetch/parse/extract stages. Part of the public JSON contract
 * (schema/webfetch-error.schema.json); treat renames as breaking changes.
 */
enum ErrorCode: string
{
    case InvalidUrl = 'invalid_url';
    case ConnectionFailed = 'connection_failed';
    case Timeout = 'timeout';
    case TooManyRedirects = 'too_many_redirects';
    case ResponseTooLarge = 'response_too_large';
    case HttpClientError = 'http_client_error';
    case HttpServerError = 'http_server_error';
    case NotHtml = 'not_html';
    case BrowserUnavailable = 'browser_unavailable';
    case BlockedUrl = 'blocked_url';
    case RobotsDisallowed = 'robots_disallowed';
    case EmptyBody = 'empty_body';
    case ParseFailure = 'parse_failure';
    case EmptyExtraction = 'empty_extraction';

    public static function fromFetchError(FetchError $error): self
    {
        // The stage enums use the same string values by design.
        return self::from($error->value);
    }

    public static function fromExtractError(ExtractError $error): self
    {
        return self::from($error->value);
    }
}
