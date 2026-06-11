<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Tests\Support;

/**
 * Builds tiny, valid PDF byte strings for tests — deterministic and offline,
 * so the PDF corpus never depends on a binary toolchain or network. Covers a
 * text page, a text-free ("scanned") page, and a decompression bomb used to
 * prove the extractor's process isolation holds.
 */
final class MinimalPdf
{
    /**
     * A one-page PDF whose content stream draws the given text, optionally
     * with Title/Author document metadata.
     */
    public static function withText(string $text, ?string $title = null, ?string $author = null): string
    {
        $content = 'BT /F1 24 Tf 72 700 Td (' . self::escape($text) . ') Tj ET';

        return self::assemble($content, $title, $author, null);
    }

    /**
     * A valid one-page PDF with no text operators — the "scanned / image-only"
     * shape that must extract to nothing.
     */
    public static function withoutText(?string $title = null): string
    {
        return self::assemble('', $title, null, null);
    }

    /**
     * A PDF whose page content is a FlateDecode stream that inflates to
     * roughly half a gigabyte. On disk it is a few hundred bytes; a parser
     * that inflates it without a memory cap dies. Built with an incremental
     * deflate context so constructing it here never costs that memory.
     */
    public static function decompressionBomb(): string
    {
        return self::assemble(self::deflateSpaces(500_000_000), null, null, 'FlateDecode');
    }

    private static function assemble(string $streamData, ?string $title, ?string $author, ?string $filter): string
    {
        $dict = '<< /Length ' . strlen($streamData) . ($filter !== null ? ' /Filter /' . $filter : '') . ' >>';

        /** @var array<int, string> $objects */
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] '
                . '/Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            5 => $dict . "\nstream\n" . $streamData . "\nendstream",
        ];

        $infoParts = [];
        if ($title !== null) {
            $infoParts[] = '/Title (' . self::escape($title) . ')';
        }
        if ($author !== null) {
            $infoParts[] = '/Author (' . self::escape($author) . ')';
        }
        $hasInfo = $infoParts !== [];
        if ($hasInfo) {
            $objects[6] = '<< ' . implode(' ', $infoParts) . ' >>';
        }

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
        }

        $size = count($objects) + 1; // + the mandatory free object 0
        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 {$size}\n0000000000 65535 f \n";
        for ($i = 1; $i < $size; $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }

        $rootInfo = '/Root 1 0 R' . ($hasInfo ? ' /Info 6 0 R' : '');
        $pdf .= "trailer\n<< /Size {$size} {$rootInfo} >>\nstartxref\n{$xrefPos}\n%%EOF";

        return $pdf;
    }

    /** Escape a PDF literal string: backslash, then parentheses. */
    private static function escape(string $text): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
    }

    /** Zlib-compress `$total` bytes of spaces without materializing them. */
    private static function deflateSpaces(int $total): string
    {
        $context = deflate_init(ZLIB_ENCODING_DEFLATE);
        if ($context === false) {
            throw new \RuntimeException('deflate_init failed');
        }

        $chunk = str_repeat(' ', 1_000_000);
        $out = '';
        for ($written = 0; $written < $total; $written += strlen($chunk)) {
            $piece = deflate_add($context, $chunk, ZLIB_NO_FLUSH);
            if ($piece !== false) {
                $out .= $piece;
            }
        }
        $tail = deflate_add($context, '', ZLIB_FINISH);
        if ($tail !== false) {
            $out .= $tail;
        }

        return $out;
    }
}
