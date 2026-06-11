<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Tests\Serializer;

use Devilsberg\Webfetch\Extractor\ExtractionStrategy;
use Devilsberg\Webfetch\Extractor\ExtractSuccess;
use Devilsberg\Webfetch\Extractor\HtmlExtractor;
use Devilsberg\Webfetch\Extractor\PageContent;
use Devilsberg\Webfetch\Extractor\PageMeta;
use Devilsberg\Webfetch\Extractor\SourceType;
use Devilsberg\Webfetch\Fetcher\FetchSuccess;
use Devilsberg\Webfetch\Serializer\JsonSerializer;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\TestCase;

final class JsonSerializerTest extends TestCase
{
    private const string FETCHED_AT = '2026-06-10T12:00:00+00:00';

    private static function successJsonFor(string $fixture): string
    {
        $html = file_get_contents(__DIR__ . '/../fixtures/' . $fixture);
        self::assertNotFalse($html);
        $fetch = new FetchSuccess(
            finalUrl: 'https://example.com/page',
            status: 200,
            contentType: 'text/html',
            charset: 'utf-8',
            body: $html,
        );

        $outcome = new HtmlExtractor()->extract($fetch);
        self::assertInstanceOf(ExtractSuccess::class, $outcome);

        return new JsonSerializer()->success(
            $outcome->content,
            $fetch->finalUrl,
            new \DateTimeImmutable(self::FETCHED_AT),
        );
    }

    private static function assertMatchesSuccessSchema(string $json): void
    {
        $schemaJson = file_get_contents(__DIR__ . '/../../schema/webfetch-success.schema.json');
        self::assertNotFalse($schemaJson);
        $schema = json_decode($schemaJson);
        self::assertIsObject($schema);

        $result = new Validator()->validate(json_decode($json), $schema);
        self::assertTrue(
            $result->isValid(),
            'Output does not match schema: ' . json_encode($result->error()?->keyword() ?? 'unknown'),
        );
    }

    public function testReadabilityOutputValidatesAgainstSchema(): void
    {
        self::assertMatchesSuccessSchema(self::successJsonFor('article.html'));
    }

    public function testFallbackOutputValidatesAgainstSchema(): void
    {
        self::assertMatchesSuccessSchema(self::successJsonFor('listing-trap.html'));
    }

    public function testMinimalOutputValidatesAgainstSchema(): void
    {
        self::assertMatchesSuccessSchema(self::successJsonFor('minimal.html'));
    }

    public function testMissingFieldsAreNullNotAbsent(): void
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode(self::successJsonFor('minimal.html'), true, 512, JSON_THROW_ON_ERROR);

        foreach (['title', 'byline', 'lang', 'published_at', 'excerpt'] as $key) {
            self::assertArrayHasKey($key, $decoded);
            self::assertNull($decoded[$key]);
        }
    }

    public function testEnvelopeFields(): void
    {
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode(self::successJsonFor('article.html'), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($decoded['ok']);
        self::assertSame(2, $decoded['schema_version']);
        self::assertSame('https://example.com/page', $decoded['url']);
        self::assertSame(self::FETCHED_AT, $decoded['fetched_at']);
        self::assertSame('html', $decoded['source_type']);
    }

    public function testPdfSourceTypeSerializesAndValidates(): void
    {
        $content = new PageContent(
            title: 'A PDF Report',
            byline: 'Jane Author',
            lang: null,
            publishedAt: null,
            excerpt: null,
            contentMarkdown: 'The body text of the PDF.',
            wordCount: 5,
            links: [],
            meta: new PageMeta(siteName: null, description: null, image: null, type: null),
            strategy: ExtractionStrategy::Pdf,
            sourceType: SourceType::Pdf,
        );

        $json = new JsonSerializer()->success($content, 'https://example.com/doc.pdf', new \DateTimeImmutable(self::FETCHED_AT));
        self::assertMatchesSuccessSchema($json);

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('pdf', $decoded['source_type']);
        self::assertSame('pdf', $decoded['extraction_strategy']);
    }

    public function testJsonIsValidUnescapedUtf8(): void
    {
        $json = self::successJsonFor('article.html');

        self::assertTrue(mb_check_encoding($json, 'UTF-8'));
        self::assertStringContainsString('https://example.com/page', $json);
        self::assertStringNotContainsString('\/', $json);
    }
}
