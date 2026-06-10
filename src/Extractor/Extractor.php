<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Extractor;

use Devilsberg\Webfetch\Fetcher\FetchSuccess;
use Devilsberg\Webfetch\Parser\Parser;
use Devilsberg\Webfetch\Parser\ParseSuccess;
use fivefilters\Readability\Configuration;
use fivefilters\Readability\ParseException;
use fivefilters\Readability\Readability;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use League\HTMLToMarkdown\HtmlConverter;

/**
 * Turns a fetched page into readable content. Primary path is
 * readability.php; when it yields nothing or only a sliver of the page
 * (the spike's index-page failure mode), a fallback builds output from
 * title + meta description + headline links instead.
 */
final class Extractor
{
    /** Below this many extracted words the readability result is distrusted. */
    private const int MIN_WORDS = 30;

    /** Extracted-to-body word ratio under which the page is treated as misextracted. */
    private const float MIN_BODY_RATIO = 0.10;

    /** Links shorter than this (in characters of text) are not "headlines". */
    private const int HEADLINE_MIN_TEXT_LENGTH = 15;

    private const int MAX_LINKS = 100;

    public function __construct(
        private readonly Parser $parser = new Parser(),
    ) {
    }

    public function extract(FetchSuccess $fetch): ExtractOutcome
    {
        $parsed = $this->parser->parse($fetch);
        if (!$parsed instanceof ParseSuccess) {
            return new ExtractFailure(ExtractError::EmptyExtraction, 'Page could not be parsed');
        }

        $document = $parsed->document;
        self::stripNoise($document);
        $meta = self::metaMap($document);
        $bodyWords = self::countWords(self::squish($document->body->textContent ?? ''));

        $contentHtml = null;
        $readabilityTitle = null;
        $readabilityAuthor = null;
        try {
            $readability = new Readability(new Configuration([
                'fixRelativeURLs' => true,
                'originalURL' => $fetch->finalUrl,
                'charThreshold' => 100,
            ]));
            $readability->parse($parsed->utf8Body);
            $contentHtml = $readability->getContent();
            $readabilityTitle = $readability->getTitle();
            $readabilityAuthor = $readability->getAuthor();
        } catch (ParseException) {
            // Nothing readable found — the fallback below still gets a shot.
        }

        $text = $contentHtml !== null ? self::squish(strip_tags($contentHtml)) : '';
        $words = self::countWords($text);
        $useFallback = $contentHtml === null
            || $words < self::MIN_WORDS
            || ($bodyWords > 0 && $words / $bodyWords < self::MIN_BODY_RATIO);

        $title = self::firstNonEmpty($readabilityTitle, $meta['og:title'] ?? null, $document->title);
        $excerpt = self::firstNonEmpty($meta['og:description'] ?? null, $meta['description'] ?? null);
        $lang = self::firstNonEmpty($document->documentElement?->getAttribute('lang'));
        $pageMeta = new PageMeta(
            siteName: $meta['og:site_name'] ?? null,
            description: $excerpt,
            image: $meta['og:image'] ?? null,
            type: $meta['og:type'] ?? null,
        );
        $publishedAt = self::firstNonEmpty($meta['article:published_time'] ?? null);

        if (!$useFallback) {
            return new ExtractSuccess(new PageContent(
                title: $title,
                byline: self::firstNonEmpty($readabilityAuthor, $meta['author'] ?? null),
                lang: $lang,
                publishedAt: $publishedAt,
                excerpt: $excerpt,
                contentMarkdown: self::toMarkdown($contentHtml),
                wordCount: $words,
                links: self::linksFromContent($contentHtml),
                meta: $pageMeta,
                strategy: ExtractionStrategy::Readability,
            ));
        }

        $links = self::headlineLinks($document, $fetch->finalUrl);
        $markdown = self::fallbackMarkdown($excerpt, $links);
        if ($title === null && $markdown === '') {
            return new ExtractFailure(
                ExtractError::EmptyExtraction,
                'Neither readable content nor title/description/links found — possibly a JavaScript-rendered page',
            );
        }

        return new ExtractSuccess(new PageContent(
            title: $title,
            byline: self::firstNonEmpty($meta['author'] ?? null),
            lang: $lang,
            publishedAt: $publishedAt,
            excerpt: $excerpt,
            contentMarkdown: $markdown,
            wordCount: self::countWords(self::squish(strip_tags($markdown))),
            links: $links,
            meta: $pageMeta,
            strategy: ExtractionStrategy::Fallback,
        ));
    }

    private static function stripNoise(\Dom\HTMLDocument $document): void
    {
        foreach ($document->querySelectorAll('script, style, noscript') as $node) {
            $node->parentNode?->removeChild($node);
        }
    }

    /**
     * @return array<string, string> meta property/name → content, lowercased keys
     */
    private static function metaMap(\Dom\HTMLDocument $document): array
    {
        $map = [];
        foreach ($document->getElementsByTagName('meta') as $element) {
            $key = $element->getAttribute('property') ?? '';
            if ($key === '') {
                $key = $element->getAttribute('name') ?? '';
            }
            $content = $element->getAttribute('content') ?? '';
            if ($key !== '' && $content !== '') {
                $map[strtolower($key)] ??= trim($content);
            }
        }

        return $map;
    }

    private static function toMarkdown(string $html): string
    {
        try {
            $converter = new HtmlConverter([
                'strip_tags' => true,
                'header_style' => 'atx',
            ]);

            return trim($converter->convert($html));
        } catch (\Throwable) {
            // Converter choked on pathological markup — plain text beats nothing.
            return self::squish(strip_tags($html));
        }
    }

    /**
     * Links inside the readability content (already made absolute by
     * fixRelativeURLs).
     *
     * @return list<Link>
     */
    private static function linksFromContent(string $contentHtml): array
    {
        try {
            $document = \Dom\HTMLDocument::createFromString($contentHtml, LIBXML_NOERROR, 'UTF-8');
        } catch (\Throwable) {
            return [];
        }

        return self::collectLinks($document, minTextLength: 1, baseUrl: null);
    }

    /**
     * Fallback link harvest: anchors with enough text to plausibly be a
     * headline or teaser, resolved against the page URL.
     *
     * @return list<Link>
     */
    private static function headlineLinks(\Dom\HTMLDocument $document, string $baseUrl): array
    {
        return self::collectLinks($document, minTextLength: self::HEADLINE_MIN_TEXT_LENGTH, baseUrl: $baseUrl);
    }

    /**
     * @return list<Link>
     */
    private static function collectLinks(\Dom\HTMLDocument $document, int $minTextLength, ?string $baseUrl): array
    {
        $links = [];
        $seen = [];
        foreach ($document->querySelectorAll('a[href]') as $anchor) {
            $href = trim($anchor->getAttribute('href') ?? '');
            $text = self::squish($anchor->textContent ?? '');
            if ($href === '' || str_starts_with($href, '#') || str_starts_with($href, 'javascript:')) {
                continue;
            }
            if (mb_strlen($text) < $minTextLength) {
                continue;
            }
            if ($baseUrl !== null) {
                try {
                    $href = (string) UriResolver::resolve(new Uri($baseUrl), new Uri($href));
                } catch (\InvalidArgumentException) {
                    continue;
                }
            }
            if (isset($seen[$href])) {
                continue;
            }
            $seen[$href] = true;
            $links[] = new Link($text, $href);
            if (count($links) >= self::MAX_LINKS) {
                break;
            }
        }

        return $links;
    }

    /**
     * @param list<Link> $links
     */
    private static function fallbackMarkdown(?string $excerpt, array $links): string
    {
        $parts = [];
        if ($excerpt !== null) {
            $parts[] = $excerpt;
        }
        if ($links !== []) {
            $items = array_map(
                static fn (Link $link): string => "- [{$link->text}]({$link->href})",
                $links,
            );
            $parts[] = "## Links\n\n" . implode("\n", $items);
        }

        return implode("\n\n", $parts);
    }

    private static function squish(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    private static function countWords(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        return (int) preg_match_all('/\S+/u', $text);
    }

    private static function firstNonEmpty(?string ...$candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if ($candidate !== null && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return null;
    }
}
