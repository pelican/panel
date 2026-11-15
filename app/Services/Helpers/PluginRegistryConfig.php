<?php

namespace App\Services\Helpers;

use Closure;
use stdClass;

/**
 * Parses PANEL_PLUGIN_REGISTRIES while the config loads (pelican/wings plugin contract v3, section R).
 *
 * The value is a JSON object keyed by namespace:
 *
 *     {"acme": {"url": "https://registry.acme.com/registry.json", "token": "${ACME_REGISTRY_TOKEN}", "headers": {"X-Api-Key": "${ACME_KEY}"}}}
 *
 * `${NAME}` in a token or header value is read from the environment here, at config load, so it works the
 * same with a cached config (`php artisan optimize`) as without one, and the secret itself can live in its
 * own variable instead of inside this JSON. This never throws: a broken value must not take the whole panel
 * down, so problems are kept as an `error` per registry (or as one string for the whole value) and reported
 * when a registry is used.
 */
final class PluginRegistryConfig
{
    public const NAMESPACE_PATTERN = '/\A[a-z0-9][a-z0-9._-]*\z/';

    private const HEADER_NAME_PATTERN = '/\A[A-Za-z0-9!#$%&\'*+.^_`|~-]+\z/';

    /**
     * The registries by namespace, or an error message when the value can't be read at all.
     *
     * @param  Closure(string): mixed  $env
     * @return array<string, array{url: string, token: ?string, headers: array<string, string>, error: ?string}>|string
     */
    public static function parse(mixed $json, Closure $env): array|string
    {
        if ($json === null || (is_string($json) && trim($json) === '')) {
            return [];
        }

        // Classify the JSON root before converting it to an array: `{"0": ...}` and `[...]` both decode to a PHP
        // list, but only the object is a set of registries (a namespace may be "0"). `[]` is accepted as empty.
        $root = is_string($json) ? json_decode($json) : null;
        if (!$root instanceof stdClass && $root !== []) {
            return 'PANEL_PLUGIN_REGISTRIES must be a JSON object of registries keyed by namespace.';
        }

        $decoded = (array) json_decode((string) $json, true);

        $registries = [];
        foreach ($decoded as $namespace => $registry) {
            $registries[(string) $namespace] = self::registry((string) $namespace, $registry, $env);
        }

        return $registries;
    }

    /**
     * @param  Closure(string): mixed  $env
     * @return array{url: string, token: ?string, headers: array<string, string>, error: ?string}
     */
    private static function registry(string $namespace, mixed $registry, Closure $env): array
    {
        $result = ['url' => '', 'token' => null, 'headers' => [], 'error' => null];

        if (preg_match(self::NAMESPACE_PATTERN, $namespace) !== 1) {
            return [...$result, 'error' => 'The namespace must be lowercase letters, digits, ".", "_" or "-".'];
        }

        if (!is_array($registry) || !is_string($registry['url'] ?? null)) {
            return [...$result, 'error' => 'The registry has no "url".'];
        }

        $result['url'] = $registry['url'];

        if (strtolower((string) parse_url($registry['url'], PHP_URL_SCHEME)) !== 'https' || blank(parse_url($registry['url'], PHP_URL_HOST))) {
            return [...$result, 'error' => 'The registry url must be https.'];
        }

        $missing = [];

        if (array_key_exists('token', $registry) && $registry['token'] !== null) {
            if (!is_string($registry['token'])) {
                return [...$result, 'error' => 'The registry "token" must be a string.'];
            }

            $result['token'] = self::expand($registry['token'], $env, $missing);
        }

        $headers = $registry['headers'] ?? [];
        if (!is_array($headers)) {
            return [...$result, 'error' => 'The registry "headers" must be an object.'];
        }

        foreach ($headers as $name => $value) {
            if (!is_string($name) || preg_match(self::HEADER_NAME_PATTERN, $name) !== 1 || !is_string($value)) {
                return [...$result, 'error' => 'The registry "headers" must map header names to strings.'];
            }

            $result['headers'][$name] = self::expand($value, $env, $missing);
        }

        if ($missing !== []) {
            // Only the variable names, never a value.
            return [...$result, 'token' => null, 'headers' => [], 'error' => 'Environment variable(s) not set: ' . implode(', ', array_unique($missing)) . '.'];
        }

        foreach ([$result['token'], ...array_values($result['headers'])] as $value) {
            if ($value !== null && preg_match('/[\r\n\0]/', $value) === 1) {
                return [...$result, 'token' => null, 'headers' => [], 'error' => 'The registry credentials contain a line break.'];
            }
        }

        return $result;
    }

    /**
     * @param  Closure(string): mixed  $env
     * @param  string[]  $missing
     */
    private static function expand(string $value, Closure $env, array &$missing): string
    {
        return (string) preg_replace_callback('/\$\{([A-Za-z_][A-Za-z0-9_]*)\}/', function (array $match) use ($env, &$missing) {
            $resolved = $env($match[1]);

            if (!is_scalar($resolved) || (string) $resolved === '') {
                $missing[] = $match[1];

                return '';
            }

            return (string) $resolved;
        }, $value);
    }
}
