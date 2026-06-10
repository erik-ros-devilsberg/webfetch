<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Extractor;

/**
 * Everything extracted from one page. Field semantics mirror the public
 * JSON schema (schema/webfetch-success.schema.json) one-to-one.
 *
 * @phpstan-type LinkList list<Link>
 */
final readonly class PageContent
{
    /**
     * @param list<Link> $links
     */
    public function __construct(
        public ?string $title,
        public ?string $byline,
        public ?string $lang,
        public ?string $publishedAt,
        public ?string $excerpt,
        public string $contentMarkdown,
        public int $wordCount,
        public array $links,
        public PageMeta $meta,
        public ExtractionStrategy $strategy,
    ) {
    }
}
