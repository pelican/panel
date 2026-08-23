<?php

namespace App\Helpers;

/**
 * Keep this free of facades and container lookups. config/passkeys.php calls it while
 * config is still loading, before the rest of the app is available.
 */
class PasskeyOrigin
{
    /**
     * Ports that are implicit for a scheme, and therefore never part of an origin.
     */
    private const DefaultPorts = [
        'http' => 80,
        'https' => 443,
    ];

    /**
     * Reduce a URL to the origin browsers report during a WebAuthn ceremony:
     * "scheme://host[:port]", without any path, query or default port.
     *
     * Scheme and host are lowercased, since that is how a browser serializes them and
     * the library compares origins as exact strings.
     *
     * Returns null when the value has no usable host.
     */
    public static function normalize(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        // Bare hosts are assumed to be https, since WebAuthn only runs in secure contexts.
        if (!str_contains($url, '://')) {
            $url = 'https://' . $url;
        }

        $parts = parse_url($url);

        if (!is_array($parts) || !isset($parts['host'])) {
            return null;
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        $port = $parts['port'] ?? null;

        $origin = $scheme . '://' . strtolower($parts['host']);

        if ($port !== null && $port !== (self::DefaultPorts[$scheme] ?? null)) {
            $origin .= ':' . $port;
        }

        return $origin;
    }

    /**
     * Normalize a list of URLs into a unique list of origins, dropping empty entries.
     *
     * @param  array<array-key, string|null>  $urls
     * @return list<string>
     */
    public static function all(array $urls): array
    {
        $origins = array_filter(array_map(self::normalize(...), $urls));

        return array_values(array_unique($origins));
    }
}
