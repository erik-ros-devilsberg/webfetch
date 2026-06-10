<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Serializer;

use Devilsberg\Webfetch\Extractor\Link;
use Devilsberg\Webfetch\Extractor\PageContent;

/**
 * Renders extracted content as the public JSON contract
 * (schema/webfetch-success.schema.json). Keys are emitted even when null —
 * consumers should never have to guess whether a field exists.
 */
final class JsonSerializer
{
    public const int SCHEMA_VERSION = 1;

    public function success(PageContent $content, string $url, \DateTimeImmutable $fetchedAt): string
    {
        $payload = [
            'ok' => true,
            'schema_version' => self::SCHEMA_VERSION,
            'url' => $url,
            'fetched_at' => $fetchedAt->format(\DateTimeInterface::ATOM),
            'title' => $content->title,
            'byline' => $content->byline,
            'lang' => $content->lang,
            'published_at' => $content->publishedAt,
            'excerpt' => $content->excerpt,
            'content_markdown' => $content->contentMarkdown,
            'word_count' => $content->wordCount,
            'links' => array_map(
                static fn (Link $link): array => ['text' => $link->text, 'href' => $link->href],
                $content->links,
            ),
            'meta' => [
                'site_name' => $content->meta->siteName,
                'description' => $content->meta->description,
                'image' => $content->meta->image,
                'type' => $content->meta->type,
            ],
            'extraction_strategy' => $content->strategy->value,
        ];

        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
