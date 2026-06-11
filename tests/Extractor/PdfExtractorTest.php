<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Tests\Extractor;

use Devilsberg\Webfetch\Extractor\ExtractError;
use Devilsberg\Webfetch\Extractor\ExtractFailure;
use Devilsberg\Webfetch\Extractor\ExtractionStrategy;
use Devilsberg\Webfetch\Extractor\ExtractSuccess;
use Devilsberg\Webfetch\Extractor\PdfExtractor;
use Devilsberg\Webfetch\Extractor\SourceType;
use Devilsberg\Webfetch\Fetcher\FetchSuccess;
use Devilsberg\Webfetch\Tests\Support\MinimalPdf;
use PHPUnit\Framework\TestCase;

final class PdfExtractorTest extends TestCase
{
    private const string SLEEP_WORKER = __DIR__ . '/../fixtures/pdf-worker-sleep.php';

    private static function pdfFetch(string $body): FetchSuccess
    {
        return new FetchSuccess(
            finalUrl: 'https://example.com/doc.pdf',
            status: 200,
            contentType: 'application/pdf',
            charset: 'binary',
            body: $body,
        );
    }

    public function testExtractsTextAndMetadata(): void
    {
        $pdf = MinimalPdf::withText(
            'Webfetch PDF extraction marker ZQX12345 end',
            title: 'Quarterly Report',
            author: 'Jane Author',
        );

        $outcome = new PdfExtractor()->extract(self::pdfFetch($pdf));

        self::assertInstanceOf(ExtractSuccess::class, $outcome);
        $content = $outcome->content;
        self::assertSame(SourceType::Pdf, $content->sourceType);
        self::assertSame(ExtractionStrategy::Pdf, $content->strategy);
        self::assertStringContainsString('ZQX12345', $content->contentMarkdown);
        self::assertSame('Quarterly Report', $content->title);
        self::assertSame('Jane Author', $content->byline);
        self::assertGreaterThan(0, $content->wordCount);
    }

    public function testPdfWithoutMetadataHasNullTitleAndByline(): void
    {
        $outcome = new PdfExtractor()->extract(self::pdfFetch(MinimalPdf::withText('plain ZQX body text')));

        self::assertInstanceOf(ExtractSuccess::class, $outcome);
        self::assertNull($outcome->content->title);
        self::assertNull($outcome->content->byline);
    }

    public function testScannedOrTextEmptyPdfReturnsEmptyExtraction(): void
    {
        $outcome = new PdfExtractor()->extract(self::pdfFetch(MinimalPdf::withoutText('Scan only')));

        self::assertInstanceOf(ExtractFailure::class, $outcome);
        self::assertSame(ExtractError::EmptyExtraction, $outcome->error);
    }

    public function testCorruptPdfReturnsParseFailure(): void
    {
        $outcome = new PdfExtractor()->extract(self::pdfFetch('%PDF-1.4 this is not a real pdf body'));

        self::assertInstanceOf(ExtractFailure::class, $outcome);
        self::assertSame(ExtractError::ParseFailed, $outcome->error);
    }

    public function testOversizedPdfRejectedBeforeParsing(): void
    {
        $outcome = new PdfExtractor(maxBytes: 16)->extract(self::pdfFetch(MinimalPdf::withText('too big for the cap')));

        self::assertInstanceOf(ExtractFailure::class, $outcome);
        self::assertSame(ExtractError::ResponseTooLarge, $outcome->error);
    }

    /**
     * The safety property: a decompression bomb that inflates to ~500MB must
     * blow up the child process (capped at 64MB), not this one. Getting a typed
     * failure back at all — rather than a killed test runner — is the proof
     * that the parent survived.
     */
    public function testDecompressionBombDoesNotCrashTheCaller(): void
    {
        $outcome = new PdfExtractor(memoryLimitMb: 64, timeoutSeconds: 25)
            ->extract(self::pdfFetch(MinimalPdf::decompressionBomb()));

        self::assertInstanceOf(ExtractFailure::class, $outcome);
        self::assertSame(ExtractError::ParseFailed, $outcome->error);
    }

    /**
     * A worker that never returns must be killed at the wall-clock deadline,
     * not hang the caller. The fixture sleeps 60s; the extractor caps at 1s.
     */
    public function testRunawayWorkerHitsTimeout(): void
    {
        $start = microtime(true);
        $outcome = new PdfExtractor(timeoutSeconds: 1, workerPath: self::SLEEP_WORKER)
            ->extract(self::pdfFetch(MinimalPdf::withText('irrelevant')));
        $elapsed = microtime(true) - $start;

        self::assertInstanceOf(ExtractFailure::class, $outcome);
        self::assertSame(ExtractError::ParseFailed, $outcome->error);
        self::assertLessThan(10.0, $elapsed, 'The timeout did not fire — the caller hung on the worker.');
    }

    public function testMissingWorkerDegradesToTypedError(): void
    {
        $outcome = new PdfExtractor(workerPath: '/nonexistent/pdf-worker.php')
            ->extract(self::pdfFetch(MinimalPdf::withText('irrelevant')));

        self::assertInstanceOf(ExtractFailure::class, $outcome);
        self::assertSame(ExtractError::ParseFailed, $outcome->error);
    }
}
