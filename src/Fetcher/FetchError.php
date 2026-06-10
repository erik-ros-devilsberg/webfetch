<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Fetcher;

/**
 * Machine-readable fetch failure reasons. The string values are the seed
 * of the public error JSON contract (story 06) — treat renames as
 * breaking changes.
 */
enum FetchError: string
{
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
}
