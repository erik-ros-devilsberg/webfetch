<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Tests\Extractor;

use Devilsberg\Webfetch\Extractor\DispatchingExtractor;
use Devilsberg\Webfetch\Extractor\ExtractError;
use Devilsberg\Webfetch\Extractor\ExtractFailure;
use Devilsberg\Webfetch\Extractor\ExtractOutcome;
use Devilsberg\Webfetch\Extractor\Extractor;
use Devilsberg\Webfetch\Extractor\ExtractSuccess;
use Devilsberg\Webfetch\Extractor\SourceType;
use Devilsberg\Webfetch\Fetcher\FetchSuccess;
use PHPUnit\Framework\TestCase;

final class DispatchingExtractorTest extends TestCase
{
    private static function fetch(string $contentType, string $body = '<x/>'): FetchSuccess
    {
        return new FetchSuccess(
            finalUrl: 'https://example.com/page',
            status: 200,
            contentType: $contentType,
            charset: 'utf-8',
            body: $body,
        );
    }

    public function testHtmlRoutesToHtmlExtractorAndTagsSourceType(): void
    {
        $html = file_get_contents(__DIR__ . '/../fixtures/article.html');
        self::assertNotFalse($html);

        $outcome = new DispatchingExtractor()->extract(self::fetch('text/html', $html));

        self::assertInstanceOf(ExtractSuccess::class, $outcome);
        self::assertSame(SourceType::Html, $outcome->content->sourceType);
    }

    public function testCharsetParameterStillRoutesToHtml(): void
    {
        $html = file_get_contents(__DIR__ . '/../fixtures/article.html');
        self::assertNotFalse($html);

        $outcome = new DispatchingExtractor()->extract(self::fetch('text/html; charset=UTF-8', $html));

        self::assertInstanceOf(ExtractSuccess::class, $outcome);
    }

    public function testUnsupportedContentTypeReturnsNotHtml(): void
    {
        $outcome = new DispatchingExtractor()->extract(self::fetch('application/json', '{}'));

        self::assertInstanceOf(ExtractFailure::class, $outcome);
        self::assertSame(ExtractError::NotHtml, $outcome->error);
    }

    public function testInjectedExtractorIsSelectedByContentType(): void
    {
        $marker = new class () implements Extractor {
            public function extract(FetchSuccess $fetch): ExtractOutcome
            {
                return new ExtractFailure(ExtractError::EmptyExtraction, 'marker');
            }
        };

        $dispatcher = new DispatchingExtractor(['application/pdf' => $marker]);
        $outcome = $dispatcher->extract(self::fetch('application/pdf', '%PDF-'));

        self::assertInstanceOf(ExtractFailure::class, $outcome);
        self::assertSame('marker', $outcome->message);
    }
}
