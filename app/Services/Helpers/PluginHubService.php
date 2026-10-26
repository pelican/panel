<?php

namespace App\Services\Helpers;

use Closure;
use DateInterval;
use DateTimeInterface;
use Exception;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Read-only client for the Pelican Hub's public plugin API. Only free plugins
 * are ever surfaced, and responses are cached because anonymous requests to the
 * Hub are heavily rate limited.
 */
class PluginHubService
{
    public const SORTS = ['popular', 'newest', 'updated', 'rating', 'name'];

    public const MAX_SEARCH_LENGTH = 100;

    /**
     * A page of free Hub plugins, or null when the Hub couldn't be reached.
     *
     * @param  string[]  $tags
     * @return LengthAwarePaginator<int, array<string, mixed>>|null
     */
    public function search(?string $query = null, string $sort = 'popular', ?string $category = null, array $tags = [], int $page = 1, int $perPage = 24): ?LengthAwarePaginator
    {
        $params = array_filter([
            'q' => filled($query) ? mb_substr(trim($query), 0, self::MAX_SEARCH_LENGTH) : null,
            'sort' => in_array($sort, self::SORTS, true) ? $sort : 'popular',
            'category' => filled($category) ? $category : null,
            'tags' => array_slice(array_values(array_filter($tags, 'filled')), 0, 10) ?: null,
            'pricing' => 'free',
            'page' => max(1, $page),
            'per_page' => min(100, max(1, $perPage)),
        ], fn ($value) => $value !== null);

        $response = $this->remember('plugins.hub.search.' . md5((string) json_encode($params)), now()->addMinutes(5), fn () => $this->get('/plugins', $params));

        if (!is_array($response) || !is_array($response['data'] ?? null)) {
            return null;
        }

        // The Hub is asked for free plugins only, but never trust it to have filtered.
        $plugins = collect($response['data'])
            ->filter(fn ($plugin) => is_array($plugin) && filled($plugin['slug'] ?? null) && $this->isFree($plugin))
            // JSON objects only ever have string keys.
            ->map(fn (array $plugin) => array_filter($plugin, 'is_string', ARRAY_FILTER_USE_KEY))
            ->values();

        return new LengthAwarePaginator(
            $plugins,
            (int) ($response['total'] ?? $plugins->count()),
            (int) ($response['per_page'] ?? $params['per_page']),
            (int) ($response['current_page'] ?? $params['page']),
        );
    }

    /** @return array<string, string> slug => name */
    public function categories(): array
    {
        return $this->taxonomy('categories');
    }

    /** @return array<string, string> slug => name */
    public function tags(): array
    {
        return $this->taxonomy('tags');
    }

    /** @return array<string, mixed>|null */
    public function find(string $slug): ?array
    {
        if (!$this->isValidSlug($slug)) {
            return null;
        }

        $plugin = $this->remember("plugins.hub.plugin.$slug", now()->addMinutes(10), fn () => $this->get('/plugins/' . rawurlencode($slug)));

        return is_array($plugin) && ($plugin['slug'] ?? null) === $slug ? $plugin : null;
    }

    /**
     * The latest stable release from the plugin's update feed. Not cached, so
     * an install always gets the current download and checksum.
     *
     * @return array{version: string, download_url: string, sha256: ?string}|null
     */
    public function latestRelease(string $slug): ?array
    {
        if (!$this->isValidSlug($slug)) {
            return null;
        }

        $url = $this->hubUrl() . '/plugins/' . rawurlencode($slug) . '/update.json';

        try {
            $feed = HubCredentials::request($url)->timeout(5)->connectTimeout(1)->get($url)->throw()->json();
        } catch (Exception $exception) {
            report($exception);

            return null;
        }

        $release = $feed['*'] ?? null;

        if (!is_array($release) || blank($release['download_url'] ?? null) || blank($release['version'] ?? null)) {
            return null;
        }

        return [
            'version' => (string) $release['version'],
            'download_url' => (string) $release['download_url'],
            'sha256' => filled($release['sha256'] ?? null) ? (string) $release['sha256'] : null,
        ];
    }

    /** @param  array<string, mixed>  $plugin */
    public function isFree(array $plugin): bool
    {
        return ($plugin['pricing_type'] ?? null) === 'free';
    }

    public function hubUrl(): string
    {
        return rtrim((string) config('panel.plugin.hub_url'), '/');
    }

    private function isValidSlug(string $slug): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9._-]*$/', $slug) === 1;
    }

    /** @return array<string, string> */
    private function taxonomy(string $type): array
    {
        $response = $this->remember("plugins.hub.$type", now()->addHour(), fn () => $this->get("/plugins/$type"));

        return collect(is_array($response) ? $response['data'] ?? [] : [])
            ->filter(fn ($item) => is_array($item) && filled($item['slug'] ?? null))
            ->mapWithKeys(fn (array $item) => [(string) $item['slug'] => (string) ($item['name'] ?? $item['slug'])])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<mixed>
     */
    private function get(string $path, array $query = []): array
    {
        $url = $this->hubUrl() . '/api' . $path;

        return HubCredentials::request($url)
            ->acceptJson()
            ->timeout(5)
            ->connectTimeout(1)
            ->get($url, $query)
            ->throw()
            ->json() ?? [];
    }

    /**
     * Caches the callback's result, but only briefly remembers a failure so a
     * Hub outage doesn't stick around for the full cache time.
     */
    private function remember(string $key, DateTimeInterface|DateInterval|int $ttl, Closure $callback): mixed
    {
        if (cache()->get($key) === 'error') {
            cache()->forget($key);
        }

        $value = cache()->remember($key, $ttl, function () use ($callback) {
            try {
                return $callback();
            } catch (Exception $exception) {
                report($exception);

                return 'error';
            }
        });

        return $value === 'error' ? null : $value;
    }
}
