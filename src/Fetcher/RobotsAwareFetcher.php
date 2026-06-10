<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Fetcher;

use GuzzleHttp\Client;

/**
 * Decorator: honors robots.txt `User-agent: *` Disallow rules.
 *
 * NOT applied by default: a single fetch a human directed an agent to make
 * is browser-like traffic, and browsers do not consult robots.txt. Wrap
 * your fetcher in this when doing crawling-shaped work (many pages from
 * sites you do not control).
 *
 * Parsing is deliberately minimal: `User-agent: *` sections, Disallow
 * prefix rules, first-match. Full REP support is a post-1.0 concern.
 */
final class RobotsAwareFetcher implements Fetcher
{
    /** @var array<string, list<string>> origin → disallow prefixes */
    private array $rulesByOrigin = [];

    private readonly \Closure $loadRobots;

    /**
     * @param \Closure(string): ?string|null $loadRobots fetches a robots.txt
     *                                                   URL, returns its body
     *                                                   or null when absent
     */
    public function __construct(
        private readonly Fetcher $inner,
        ?\Closure $loadRobots = null,
    ) {
        $this->loadRobots = $loadRobots ?? static function (string $url): ?string {
            try {
                $response = new Client()->request('GET', $url, [
                    'http_errors' => false,
                    'timeout' => 5,
                    'connect_timeout' => 5,
                ]);

                return $response->getStatusCode() === 200 ? (string) $response->getBody() : null;
            } catch (\Throwable) {
                return null;
            }
        };
    }

    public function fetch(string $url, ?FetchOptions $options = null): FetchOutcome
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        if (is_string($scheme) && is_string($host)) {
            $origin = strtolower("{$scheme}://{$host}");
            $path = parse_url($url, PHP_URL_PATH);
            $path = is_string($path) && $path !== '' ? $path : '/';

            foreach ($this->rulesFor($origin) as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    return new FetchFailure(
                        FetchError::RobotsDisallowed,
                        "robots.txt of {$origin} disallows '{$path}' (rule: Disallow: {$prefix})",
                    );
                }
            }
        }

        return $this->inner->fetch($url, $options);
    }

    /**
     * @return list<string>
     */
    private function rulesFor(string $origin): array
    {
        return $this->rulesByOrigin[$origin] ??= self::parse(($this->loadRobots)("{$origin}/robots.txt"));
    }

    /**
     * @return list<string>
     */
    private static function parse(?string $robots): array
    {
        if ($robots === null) {
            return [];
        }

        $rules = [];
        $appliesToUs = false;
        foreach (preg_split('/\R/', $robots) ?: [] as $line) {
            $line = trim((string) preg_replace('/#.*$/', '', $line));
            if ($line === '') {
                continue;
            }
            if (preg_match('/^user-agent:\s*(.+)$/i', $line, $m) === 1) {
                $appliesToUs = trim($m[1]) === '*';
                continue;
            }
            if ($appliesToUs && preg_match('/^disallow:\s*(.*)$/i', $line, $m) === 1) {
                $prefix = trim($m[1]);
                if ($prefix !== '') {
                    $rules[] = $prefix;
                }
            }
        }

        return $rules;
    }
}
