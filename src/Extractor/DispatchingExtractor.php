<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Extractor;

use Devilsberg\Webfetch\Fetcher\FetchSuccess;

/**
 * Routes a fetched response to the extractor that understands its content
 * type. Keeps "how bytes are interpreted" out of the fetch layer: the
 * fetcher only reports a content type, this picks the matching extractor.
 *
 * An unsupported content type returns a NotHtml failure — the same public
 * error the static fetcher's HTML gate produces — so behaviour is unchanged
 * for non-HTML responses that reach this far.
 */
final class DispatchingExtractor implements Extractor
{
    /** @var array<string, Extractor> normalized content type → extractor */
    private readonly array $extractors;

    /**
     * @param array<string, Extractor>|null $extractors content type → extractor,
     *        defaulting to the built-in HTML extractor for text/html
     */
    public function __construct(?array $extractors = null)
    {
        if ($extractors === null) {
            $html = new HtmlExtractor();
            $extractors = [
                'text/html' => $html,
                'application/xhtml+xml' => $html,
                'application/pdf' => new PdfExtractor(),
            ];
        }

        $this->extractors = $extractors;
    }

    public function extract(FetchSuccess $fetch): ExtractOutcome
    {
        $contentType = self::normalize($fetch->contentType);
        $extractor = $this->extractors[$contentType] ?? null;
        if ($extractor === null) {
            return new ExtractFailure(
                ExtractError::NotHtml,
                "No extractor for content type '{$contentType}'",
            );
        }

        return $extractor->extract($fetch);
    }

    /**
     * Drop any parameters (e.g. "; charset=utf-8") and lowercase, matching
     * how StaticFetcher records the content type.
     */
    private static function normalize(string $contentType): string
    {
        return strtolower(trim(explode(';', $contentType)[0]));
    }
}
