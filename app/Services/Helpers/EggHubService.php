<?php

namespace App\Services\Helpers;

use App\Enums\EggFormat;
use App\Exceptions\Service\InvalidFileUploadException;
use App\Models\Egg;
use App\Services\Eggs\Sharing\EggImporterService;
use Exception;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;

/**
 * Finds eggs in the Pelican Hub's public egg API by uuid, and imports them.
 */
class EggHubService
{
    public function __construct(private readonly EggImporterService $importer) {}

    /**
     * The Hub's egg with the uuid, or null when the Hub doesn't have it or
     * couldn't be reached. Results are cached, since anonymous requests to the
     * Hub are rate limited.
     *
     * @return array{uuid: string, name: string, slug: string, url: ?string, download_url: string}|null
     */
    public function findByUuid(string $uuid): ?array
    {
        if (!Str::isUuid($uuid)) {
            return null;
        }

        $uuid = strtolower($uuid);
        $key = "eggs.hub.uuid.$uuid";

        if (cache()->get($key) === 'error') {
            cache()->forget($key);
        }

        $egg = cache()->remember($key, now()->addMinutes(10), function () use ($uuid) {
            try {
                $url = $this->hubUrl() . '/api/eggs';
                $json = HubCredentials::request($url)
                    ->acceptJson()
                    ->timeout(5)
                    ->connectTimeout(1)
                    ->get($url, ['uuid' => $uuid, 'per_page' => 1])
                    ->throw()
                    ->json();
            } catch (Exception $exception) {
                report($exception);

                return 'error';
            }

            // The Hub filters by uuid, but never trust it to have.
            $match = collect(is_array($json) ? ($json['data'] ?? []) : [])
                ->first(fn ($egg) => is_array($egg) && is_string($egg['uuid'] ?? null) && strtolower($egg['uuid']) === $uuid);

            return $match && $this->isHubUrl($match['download_url'] ?? null) ? [
                'uuid' => $uuid,
                'name' => (string) ($match['name'] ?? $uuid),
                'slug' => (string) ($match['slug'] ?? ''),
                'url' => is_string($match['url'] ?? null) ? $match['url'] : null,
                'download_url' => $match['download_url'],
            ] : 'not_found';
        });

        return is_array($egg) ? $egg : null;
    }

    /**
     * Imports the Hub's egg with the uuid. The file must be the egg asked for, so
     * a different egg is never installed in its place.
     *
     * @throws Exception
     */
    public function install(string $uuid): Egg
    {
        $hubEgg = $this->findByUuid($uuid);
        throw_unless($hubEgg, new InvalidFileUploadException(trans('admin/backup.restore.hub_egg_not_found')));

        $response = HubCredentials::request($hubEgg['download_url'])
            ->timeout(10)
            ->connectTimeout(2)
            ->get($hubEgg['download_url']);

        if ($response->failed()) {
            throw new RequestException($response);
        }

        $format = $this->format((string) $response->header('Content-Type'), (string) $response->header('Content-Disposition'));
        $content = $response->body();

        $parsed = $this->importer->parse($content, $format);
        throw_unless(strtolower((string) ($parsed['uuid'] ?? '')) === $hubEgg['uuid'], new InvalidFileUploadException(trans('admin/backup.restore.hub_egg_mismatch')));

        return $this->importer->fromContent($content, $format);
    }

    public function hubUrl(): string
    {
        return rtrim((string) config('panel.plugin.hub_url'), '/');
    }

    /**
     * The Hub serves eggs as YAML or JSON. The filename is checked when the
     * content type doesn't say.
     */
    private function format(string $contentType, string $disposition): EggFormat
    {
        $contentType = strtolower($contentType);

        return match (true) {
            str_contains($contentType, 'json') => EggFormat::JSON,
            str_contains($contentType, 'yaml'), str_contains($contentType, 'yml') => EggFormat::YAML,
            (bool) preg_match('/filename="?[^";]+\.json"?/i', $disposition) => EggFormat::JSON,
            default => EggFormat::YAML,
        };
    }

    /**
     * Eggs are only downloaded from the configured Hub itself.
     */
    private function isHubUrl(mixed $url): bool
    {
        if (!is_string($url)) {
            return false;
        }

        $hub = parse_url($this->hubUrl());
        $target = parse_url($url);

        return is_array($hub) && is_array($target)
            && strtolower($target['scheme'] ?? '') === strtolower($hub['scheme'] ?? '')
            && strtolower($target['host'] ?? '') === strtolower($hub['host'] ?? '')
            && ($target['port'] ?? null) === ($hub['port'] ?? null);
    }
}
