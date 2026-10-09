<?php

namespace App\Services\Helpers;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Attaches this panel's Pelican Hub key to requests bound for the Hub, so the
 * Hub can serve private beta builds the panel's owner explicitly enrolled in.
 * The key is only sent over https to the configured Hub's exact host and port,
 * and credentialed requests never follow redirects, so it can't leak elsewhere.
 */
class HubCredentials
{
    /**
     * An HTTP client for the URL, carrying the Hub key only when the URL is the Hub.
     */
    public static function request(string $url): PendingRequest
    {
        $headers = self::headersFor($url);

        if ($headers === []) {
            return Http::withOptions([]);
        }

        return Http::withHeaders($headers)->withoutRedirecting();
    }

    /** @return array<string, string> */
    public static function headersFor(string $url): array
    {
        $key = config('panel.plugin.hub_api_key');
        $hub = self::origin((string) config('panel.plugin.hub_url'));

        if (blank($key) || $hub === null) {
            return [];
        }

        if (self::origin($url) !== $hub) {
            return [];
        }

        return ['X-Panel-Api-Key' => (string) $key];
    }

    /**
     * Lowercased `host:port` for an https URL (default port 443), or null for
     * anything that isn't https.
     */
    private static function origin(string $url): ?string
    {
        $parts = parse_url($url);

        if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || blank($parts['host'] ?? null)) {
            return null;
        }

        return strtolower($parts['host']) . ':' . ($parts['port'] ?? 443);
    }
}
