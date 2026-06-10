<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Cli;

use Devilsberg\Webfetch\Fetcher\Fetcher;
use Devilsberg\Webfetch\Fetcher\FetchOptions;
use Devilsberg\Webfetch\Webfetch;

/**
 * The CLI, minus process concerns: argv in, exit code out, output to
 * injected streams. bin/webfetch is a thin shim around this so tests can
 * drive it in-process.
 */
final class Command
{
    private const string USAGE = <<<'TXT'
        Usage: webfetch [options] <url>

        Fetch a webpage and print its readable content as JSON (see
        schema/ for the output contract). Prints error JSON and exits 1
        when the page cannot be fetched or extracted.

        Options:
          --timeout=<seconds>          total transfer timeout (default 30)
          --connect-timeout=<seconds>  connection timeout (default 10)
          --max-redirects=<n>          redirect limit (default 5)
          --max-bytes=<n>              response size cap (default 10000000)
          --user-agent=<string>        User-Agent header
          -h, --help                   show this help

        Exit codes: 0 success, 1 fetch/extract error, 2 usage error.
        TXT;

    public function __construct(
        private readonly ?Fetcher $fetcher = null,
        private readonly ?\DateTimeImmutable $fetchedAt = null,
    ) {
    }

    /**
     * @param list<string> $argv     full argv, program name first
     * @param resource     $stdout
     * @param resource     $stderr
     */
    public function run(array $argv, $stdout, $stderr): int
    {
        $defaults = new FetchOptions();
        $totalTimeout = $defaults->totalTimeout;
        $connectTimeout = $defaults->connectTimeout;
        $maxRedirects = $defaults->maxRedirects;
        $maxBytes = $defaults->maxBytes;
        $userAgent = $defaults->userAgent;
        $url = null;

        foreach (array_slice($argv, 1) as $arg) {
            if ($arg === '-h' || $arg === '--help') {
                fwrite($stdout, self::USAGE . "\n");

                return 0;
            }
            if (str_starts_with($arg, '--')) {
                [$flag, $value] = array_pad(explode('=', $arg, 2), 2, null);
                $error = match ($flag) {
                    '--timeout' => self::floatFlag($flag, $value, $totalTimeout),
                    '--connect-timeout' => self::floatFlag($flag, $value, $connectTimeout),
                    '--max-redirects' => self::intFlag($flag, $value, $maxRedirects),
                    '--max-bytes' => self::intFlag($flag, $value, $maxBytes),
                    '--user-agent' => self::stringFlag($flag, $value, $userAgent),
                    default => "Unknown option: {$flag}",
                };
                if ($error !== null) {
                    return self::usageError($stderr, $error);
                }
                continue;
            }
            if ($url !== null) {
                return self::usageError($stderr, 'Exactly one URL expected');
            }
            $url = $arg;
        }

        if ($url === null) {
            return self::usageError($stderr, 'Missing URL');
        }

        $json = Webfetch::create(fetcher: $this->fetcher, fetchedAt: $this->fetchedAt)->fetch($url, new FetchOptions(
            connectTimeout: $connectTimeout,
            totalTimeout: $totalTimeout,
            maxRedirects: $maxRedirects,
            maxBytes: $maxBytes,
            userAgent: $userAgent,
        ));
        fwrite($stdout, $json . "\n");

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($json, true);

        return ($decoded['ok'] ?? false) === true ? 0 : 1;
    }

    /**
     * @param resource $stderr
     */
    private static function usageError($stderr, string $message): int
    {
        fwrite($stderr, $message . "\n\n" . self::USAGE . "\n");

        return 2;
    }

    private static function floatFlag(string $flag, ?string $value, float &$target): ?string
    {
        if ($value === null || !is_numeric($value)) {
            return "Option {$flag} needs a numeric value";
        }
        $target = (float) $value;

        return null;
    }

    private static function intFlag(string $flag, ?string $value, int &$target): ?string
    {
        if ($value === null || !ctype_digit($value)) {
            return "Option {$flag} needs a positive integer value";
        }
        $target = (int) $value;

        return null;
    }

    private static function stringFlag(string $flag, ?string $value, string &$target): ?string
    {
        if ($value === null || $value === '') {
            return "Option {$flag} needs a value";
        }
        $target = $value;

        return null;
    }
}
