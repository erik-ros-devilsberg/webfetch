<?php

declare(strict_types=1);

/**
 * Isolated PDF text-extraction worker. Runs as a short-lived child process
 * (see Devilsberg\Webfetch\Extractor\PdfExtractor) so that a malicious or
 * malformed PDF — decompression bomb, corrupted xref, pathological font —
 * blows up *this* process, not the caller's. The parent caps memory and
 * wall-clock time on the subprocess and reads the JSON result from stdout.
 *
 * Usage: php pdf-worker.php <autoload.php> <pdf-file>
 * Output (stdout): {"ok":true,"text":"...","title":"...","author":"..."}
 *                  or {"ok":false,"error":"..."} on a parse failure.
 *
 * This file lives in bin/ (outside the PSR-4 src/ tree) because it is a
 * runnable script with side effects, not an autoloadable class.
 */

$autoload = $argv[1] ?? '';
$pdfFile = $argv[2] ?? '';

if ($autoload === '' || !is_file($autoload)) {
    fwrite(STDOUT, json_encode(['ok' => false, 'error' => 'autoloader not found']));
    exit(1);
}
require $autoload;

if ($pdfFile === '' || !is_file($pdfFile)) {
    fwrite(STDOUT, json_encode(['ok' => false, 'error' => 'pdf file not found']));
    exit(1);
}

try {
    $config = new \Smalot\PdfParser\Config();
    // Don't keep raw image bytes around — pure noise for text extraction and
    // a memory sink on image-heavy PDFs.
    $config->setRetainImageContent(false);

    $document = new \Smalot\PdfParser\Parser([], $config)->parseFile($pdfFile);
    $details = $document->getDetails();

    $payload = [
        'ok' => true,
        'text' => $document->getText(),
        'title' => is_string($details['Title'] ?? null) ? $details['Title'] : null,
        'author' => is_string($details['Author'] ?? null) ? $details['Author'] : null,
    ];
    fwrite(STDOUT, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    exit(0);
} catch (\Throwable $e) {
    fwrite(STDOUT, json_encode(['ok' => false, 'error' => $e->getMessage()]));
    exit(1);
}
