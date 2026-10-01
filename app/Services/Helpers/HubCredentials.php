<?php

namespace App\Services\Helpers;

/**
 * Attaches this panel's Pelican Hub key to requests bound for the Hub, so the
 * Hub can serve private beta builds the panel's owner explicitly enrolled in.
 * The key is never sent to any other host or over plain http.
 */
class HubCredentials
{
    /** @return array<string, string> */
    public static function headersFor(string $url): array
    {
        $key = config('panel.plugin.hub_api_key');
        $hubHost = parse_url((string) config('panel.plugin.hub_url'), PHP_URL_HOST);

        if (blank($key) || !is_string($hubHost)) {
            return [];
        }

        if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return [];
        }

        if (strtolower((string) parse_url($url, PHP_URL_HOST)) !== strtolower($hubHost)) {
            return [];
        }

        return ['X-Panel-Api-Key' => (string) $key];
    }
}
