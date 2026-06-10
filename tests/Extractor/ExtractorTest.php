<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Tests\Extractor;

use Devilsberg\Webfetch\Extractor\ExtractError;
use Devilsberg\Webfetch\Extractor\ExtractFailure;
use Devilsberg\Webfetch\Extractor\ExtractionStrategy;
use Devilsberg\Webfetch\Extractor\Extractor;
use Devilsberg\Webfetch\Extractor\ExtractSuccess;
use Devilsberg\Webfetch\Fetcher\FetchSuccess;
use PHPUnit\Framework\TestCase;

final class ExtractorTest extends TestCase
{
    private static function fetchFixture(string $name, string $url = 'https://example.com/page'): FetchSuccess
    {
        $html = file_get_contents(__DIR__ . '/../fixtures/' . $name);
        self::assertNotFalse($html);

        return new FetchSuccess(
            finalUrl: $url,
            status: 200,
            contentType: 'text/html',
            charset: 'utf-8',
            body: $html,
        );
    }

    public function testArticleExtractsFullMetadata(): void
    {
        $outcome = new Extractor()->extract(self::fetchFixture('article.html'));

        self::assertInstanceOf(ExtractSuccess::class, $outcome);
        $content = $outcome->content;
        self::assertSame(ExtractionStrategy::Readability, $content->strategy);
        self::assertNotNull($content->title);
        self::assertStringContainsString('The Art of Static Fetching', $content->title);
        self::assertSame('Jane Doe', $content->byline);
        self::assertSame('en', $content->lang);
        self::assertSame('2026-01-15T09:00:00+00:00', $content->publishedAt);
        self::assertNotNull($content->excerpt);
        self::assertSame('Example Journal', $content->meta->siteName);
        self::assertSame('article', $content->meta->type);
        self::assertSame('https://example.com/img/static-fetching.png', $content->meta->image);
        self::assertGreaterThan(100, $content->wordCount);
    }

    public function testArticleMarkdownPreservesStructure(): void
    {
        $outcome = new Extractor()->extract(self::fetchFixture('article.html'));

        self::assertInstanceOf(ExtractSuccess::class, $outcome);
        $markdown = $outcome->content->contentMarkdown;
        self::assertStringContainsString('## Why latency matters more than completeness', $markdown);
        self::assertStringContainsString('- Fetch statically first, always', $markdown);
        self::assertStringContainsString('$result = $static->fetch($url);', $markdown);
        self::assertStringContainsString('**exactly the point**', $markdown);
        self::assertStringContainsString('[the extraction deep dive](https://example.com/extraction-deep-dive)', $markdown);
    }

    public function testArticleScriptContentIsAbsent(): void
    {
        $outcome = new Extractor()->extract(self::fetchFixture('article.html'));

        self::assertInstanceOf(ExtractSuccess::class, $outcome);
        self::assertStringNotContainsString('analyticsBeacon', $outcome->content->contentMarkdown);
    }

    public function testArticleCollectsContentLinks(): void
    {
        $outcome = new Extractor()->extract(self::fetchFixture('article.html'));

        self::assertInstanceOf(ExtractSuccess::class, $outcome);
        $hrefs = array_map(static fn ($link) => $link->href, $outcome->content->links);
        self::assertContains('https://example.com/extraction-deep-dive', $hrefs);
    }

    public function testDocsPageExtracts(): void
    {
        $outcome = new Extractor()->extract(self::fetchFixture('docs.html'));

        self::assertInstanceOf(ExtractSuccess::class, $outcome);
        $content = $outcome->content;
        self::assertSame(ExtractionStrategy::Readability, $content->strategy);
        self::assertStringContainsString('fetch(string $url', $content->contentMarkdown);
        self::assertStringContainsString('Redirects are followed', $content->contentMarkdown);
    }

    public function testMinimalPageExtractsWithNullMetadata(): void
    {
        $outcome = new Extractor()->extract(self::fetchFixture('minimal.html'));

        self::assertInstanceOf(ExtractSuccess::class, $outcome);
        $content = $outcome->content;
        self::assertStringContainsString('one honest paragraph', $content->contentMarkdown);
        self::assertGreaterThan(0, $content->wordCount);
        self::assertNull($content->byline);
        self::assertNull($content->publishedAt);
        self::assertNull($content->meta->siteName);
    }

    public function testListingPageTriggersFallback(): void
    {
        $outcome = new Extractor()->extract(self::fetchFixture('listing-trap.html'));

        self::assertInstanceOf(ExtractSuccess::class, $outcome);
        $content = $outcome->content;
        self::assertSame(ExtractionStrategy::Fallback, $content->strategy);
        self::assertNotNull($content->title);
        self::assertStringContainsString('Example Tech News', $content->title);
        self::assertNotEmpty($content->links);
        self::assertStringContainsString('PHP 8.5 preview', $content->contentMarkdown);
    }

    public function testFallbackResolvesRelativeLinkUrls(): void
    {
        $outcome = new Extractor()->extract(self::fetchFixture('listing-trap.html', 'https://news.example.com/front'));

        self::assertInstanceOf(ExtractSuccess::class, $outcome);
        $hrefs = array_map(static fn ($link) => $link->href, $outcome->content->links);
        self::assertContains('https://news.example.com/2026/06/php-85-preview', $hrefs);
    }

    public function testEmptyShellFailsWithEmptyExtraction(): void
    {
        $outcome = new Extractor()->extract(self::fetchFixture('empty-shell.html'));

        self::assertInstanceOf(ExtractFailure::class, $outcome);
        self::assertSame(ExtractError::EmptyExtraction, $outcome->error);
    }
}
