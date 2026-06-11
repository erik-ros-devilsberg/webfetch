<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Extractor;

use Devilsberg\Webfetch\Fetcher\FetchSuccess;

/**
 * Extracts readable text + metadata from `application/pdf` bytes.
 *
 * Parsing untrusted PDFs in-process is unsafe: a decompression bomb can
 * exhaust memory (an uncatchable fatal in PHP) and a corrupted xref can spin
 * forever — either would break the never-throw facade. So the actual parse
 * runs in a short-lived child PHP process (bin/pdf-worker.php) with a hard
 * memory cap and a wall-clock timeout. If the child dies, the parent simply
 * reports a typed failure; it is never taken down with it.
 *
 * The default path needs no system binary — only smalot/pdfparser (a runtime
 * dependency) and the PHP binary already running. Where the subprocess cannot
 * be launched at all (proc_open disabled, autoloader not found), extraction
 * degrades to a typed `parse_failure`, mirroring ChromeFetcher's
 * browser-unavailable degradation.
 */
final class PdfExtractor implements Extractor
{
    private readonly string $workerPath;
    private readonly string $autoloadPath;

    /**
     * @param int          $maxBytes        wire-size cap; larger payloads are rejected before the subprocess runs
     * @param int          $memoryLimitMb   memory ceiling for the parsing child process
     * @param int          $timeoutSeconds  wall-clock ceiling for the parsing child process
     * @param string       $phpBinary       PHP CLI used to run the worker
     * @param string|null  $workerPath      override the worker script (tests inject hostile workers here)
     * @param string|null  $autoloadPath    override Composer autoloader discovery
     */
    public function __construct(
        private readonly int $maxBytes = 25_000_000,
        private readonly int $memoryLimitMb = 256,
        private readonly int $timeoutSeconds = 20,
        private readonly string $phpBinary = PHP_BINARY,
        ?string $workerPath = null,
        ?string $autoloadPath = null,
    ) {
        $this->workerPath = $workerPath ?? __DIR__ . '/../../bin/pdf-worker.php';
        $this->autoloadPath = $autoloadPath ?? (self::discoverAutoload() ?? '');
    }

    public function extract(FetchSuccess $fetch): ExtractOutcome
    {
        if (strlen($fetch->body) > $this->maxBytes) {
            return new ExtractFailure(
                ExtractError::ResponseTooLarge,
                "PDF exceeds the {$this->maxBytes}-byte extraction cap",
            );
        }
        if (!function_exists('proc_open') || $this->autoloadPath === '' || !is_file($this->workerPath)) {
            return new ExtractFailure(
                ExtractError::ParseFailed,
                'PDF extraction subprocess is unavailable in this environment',
            );
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'webfetch_pdf_');
        if ($tmpFile === false) {
            return new ExtractFailure(ExtractError::ParseFailed, 'Could not allocate a temp file for PDF parsing');
        }

        try {
            file_put_contents($tmpFile, $fetch->body);
            $result = $this->runWorker($tmpFile);
        } finally {
            @unlink($tmpFile);
        }

        if ($result === null) {
            return new ExtractFailure(
                ExtractError::ParseFailed,
                'PDF parsing did not complete (timed out, ran out of memory, or crashed)',
            );
        }
        if ($result['ok'] !== true) {
            return new ExtractFailure(ExtractError::ParseFailed, 'PDF parse failed: ' . $result['error']);
        }

        $text = self::normalizeText($result['text']);
        if ($text === '') {
            return new ExtractFailure(
                ExtractError::EmptyExtraction,
                'PDF yielded no extractable text — likely scanned or image-only',
            );
        }

        return new ExtractSuccess(new PageContent(
            title: self::cleanString($result['title']),
            byline: self::cleanString($result['author']),
            lang: null,
            publishedAt: null,
            excerpt: null,
            contentMarkdown: $text,
            wordCount: self::countWords($text),
            links: [],
            meta: new PageMeta(siteName: null, description: null, image: null, type: null),
            strategy: ExtractionStrategy::Pdf,
            sourceType: SourceType::Pdf,
        ));
    }

    /**
     * Runs the worker as an isolated child process, enforcing the memory and
     * time caps. Returns the decoded result, or null when the child failed to
     * produce a usable answer (killed, crashed, or non-JSON output).
     *
     * @return array{ok: bool, text: string, title: ?string, author: ?string, error: string}|null
     */
    private function runWorker(string $pdfFile): ?array
    {
        $command = [
            $this->phpBinary,
            '-d', 'memory_limit=' . $this->memoryLimitMb . 'M',
            '-d', 'display_errors=0',
            $this->workerPath,
            $this->autoloadPath,
            $pdfFile,
        ];

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $pipes = [];
        $process = proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            return null;
        }

        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $deadline = microtime(true) + $this->timeoutSeconds;
        $exitCode = -1;
        while (true) {
            $status = proc_get_status($process);
            $stdout .= (string) stream_get_contents($pipes[1]);
            if (!$status['running']) {
                $exitCode = $status['exitcode'];
                break;
            }
            if (microtime(true) >= $deadline) {
                proc_terminate($process, 9);
                proc_close($process);

                return null;
            }
            usleep(10_000);
        }

        $stdout .= (string) stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        if ($exitCode !== 0) {
            return null;
        }

        return self::decodeResult($stdout);
    }

    /**
     * @return array{ok: bool, text: string, title: ?string, author: ?string, error: string}|null
     */
    private static function decodeResult(string $stdout): ?array
    {
        try {
            /** @var mixed $decoded */
            $decoded = json_decode($stdout, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }
        if (!is_array($decoded) || !array_key_exists('ok', $decoded)) {
            return null;
        }

        return [
            'ok' => $decoded['ok'] === true,
            'text' => is_string($decoded['text'] ?? null) ? $decoded['text'] : '',
            'title' => is_string($decoded['title'] ?? null) ? $decoded['title'] : null,
            'author' => is_string($decoded['author'] ?? null) ? $decoded['author'] : null,
            'error' => is_string($decoded['error'] ?? null) ? $decoded['error'] : 'unknown error',
        ];
    }

    /**
     * Collapse the noisy whitespace PDF text extraction produces: trim, drop
     * runs of blank lines down to a single paragraph break, strip trailing
     * spaces. Plain text doubles as valid markdown.
     */
    private static function normalizeText(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $text = (string) preg_replace('/[ \t]+\n/', "\n", $text);
        $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    private static function cleanString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return $value === '' ? null : $value;
    }

    private static function countWords(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        return (int) preg_match_all('/\S+/u', $text);
    }

    private static function discoverAutoload(): ?string
    {
        $dir = __DIR__;
        for ($i = 0; $i < 8; $i++) {
            $candidate = $dir . '/vendor/autoload.php';
            if (is_file($candidate)) {
                return $candidate;
            }
            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }
            $dir = $parent;
        }

        return null;
    }
}
