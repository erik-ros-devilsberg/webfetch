<?php

declare(strict_types=1);

namespace Devilsberg\Webfetch\Fetcher;

/**
 * SSRF guard: refuses URLs that point at private, loopback, or link-local
 * targets. An agent can be talked into fetching attacker-chosen URLs, so
 * internal services must be unreachable by default.
 *
 * Best-effort by design: the hostname is resolved here and again by the
 * HTTP client (a DNS-rebinding window exists), and only A records are
 * checked for hostnames. For hard isolation run webfetch in a network
 * namespace without internal routes.
 */
final class UrlGuard
{
    /**
     * Returns the reason the URL is blocked, or null when it is fine.
     */
    public static function check(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return 'URL has no host';
        }

        $host = strtolower(trim($host, '[]'));
        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            return "Host '{$host}' is local";
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::isPublicIp($host) ? null : "IP '{$host}' is in a private or reserved range";
        }

        // Hostname: best-effort A-record check.
        $resolved = gethostbyname($host);
        if ($resolved !== $host && filter_var($resolved, FILTER_VALIDATE_IP) !== false && !self::isPublicIp($resolved)) {
            return "Host '{$host}' resolves to private or reserved IP '{$resolved}'";
        }

        return null;
    }

    private static function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
