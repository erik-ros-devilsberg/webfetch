<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Tests\Parser;

use Devilsberg\Webfetch\Fetcher\FetchSuccess;
use Devilsberg\Webfetch\Parser\ParseError;
use Devilsberg\Webfetch\Parser\ParseFailure;
use Devilsberg\Webfetch\Parser\Parser;
use Devilsberg\Webfetch\Parser\ParseSuccess;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    private static function fetchSuccess(string $body, string $charset = 'utf-8'): FetchSuccess
    {
        return new FetchSuccess(
            finalUrl: 'https://example.com/',
            status: 200,
            contentType: 'text/html',
            charset: $charset,
            body: $body,
        );
    }

    private static function fixture(string $name): string
    {
        $html = file_get_contents(__DIR__ . '/../fixtures/' . $name);
        self::assertNotFalse($html);

        return $html;
    }

    public function testParsesValidHtml(): void
    {
        $outcome = new Parser()->parse(self::fetchSuccess(self::fixture('valid.html')));

        self::assertInstanceOf(ParseSuccess::class, $outcome);
        self::assertSame('Valid Page', $outcome->document->title);
        self::assertSame('Nothing wrong here.', $outcome->document->querySelector('#intro')?->textContent);
    }

    public function testParsesBrokenMarkupWithoutWarnings(): void
    {
        $outcome = new Parser()->parse(self::fetchSuccess(self::fixture('broken.html')));

        self::assertInstanceOf(ParseSuccess::class, $outcome);
        self::assertSame('Broken Page', $outcome->document->title);
        self::assertStringContainsString('Unclosed paragraph', $outcome->document->body->textContent ?? '');
        self::assertStringContainsString('item two', $outcome->document->body->textContent ?? '');
    }

    public function testConvertsNonUtf8BodyToUtf8(): void
    {
        $utf8 = '<html><head><title>Café</title></head><body><p>déjà vu</p></body></html>';
        $latin1 = mb_convert_encoding($utf8, 'ISO-8859-1', 'UTF-8');

        $outcome = new Parser()->parse(self::fetchSuccess($latin1, 'iso-8859-1'));

        self::assertInstanceOf(ParseSuccess::class, $outcome);
        self::assertSame('Café', $outcome->document->title);
        $text = $outcome->document->body->textContent ?? '';
        self::assertStringContainsString('déjà vu', $text);
        self::assertTrue(mb_check_encoding($text, 'UTF-8'));
    }

    public function testInvalidCharsetLabelFallsBackToUtf8(): void
    {
        $body = '<html><head><title>héllo</title></head><body><p>héllo wörld</p></body></html>';

        $outcome = new Parser()->parse(self::fetchSuccess($body, 'definitely-not-a-charset'));

        self::assertInstanceOf(ParseSuccess::class, $outcome);
        self::assertStringContainsString('héllo wörld', $outcome->document->body->textContent ?? '');
    }

    public function testEmptyBodyIsTypedFailure(): void
    {
        $outcome = new Parser()->parse(self::fetchSuccess("  \n\t "));

        self::assertInstanceOf(ParseFailure::class, $outcome);
        self::assertSame(ParseError::EmptyBody, $outcome->error);
    }
}
