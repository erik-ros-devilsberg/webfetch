<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Parser;

use Devilsberg\Webfetch\Fetcher\FetchSuccess;

/**
 * Turns a fetched HTML body into a queryable \Dom\HTMLDocument (PHP 8.4
 * lexbor parser). The body is normalized to UTF-8 first so downstream
 * extraction never deals with mixed encodings.
 */
final class Parser
{
    public function parse(FetchSuccess $fetch): ParseOutcome
    {
        $body = $fetch->body;
        if (trim($body) === '') {
            return new ParseFailure(ParseError::EmptyBody, 'Response body is empty');
        }

        $body = self::toUtf8($body, $fetch->charset);

        try {
            $document = \Dom\HTMLDocument::createFromString($body, LIBXML_NOERROR, 'UTF-8');
        } catch (\Throwable $e) {
            return new ParseFailure(ParseError::ParseFailed, $e->getMessage());
        }

        return new ParseSuccess($document, $body);
    }

    private static function toUtf8(string $body, string $charset): string
    {
        $charset = strtolower($charset);
        if ($charset === 'utf-8' || $charset === 'utf8') {
            return $body;
        }

        try {
            $converted = mb_convert_encoding($body, 'UTF-8', $charset);
        } catch (\ValueError) {
            // Unknown charset label — treat the body as UTF-8 and let the
            // parser make the best of it.
            return $body;
        }

        return is_string($converted) ? $converted : $body;
    }
}
