<?php

namespace App\Services\Helpers;

use Exception;
use Illuminate\Support\Facades\Http;

class SoftwareVersionService
{
    public function latestPanelVersionChangelog(): ?string
    {
        return $this->latestRelease('panel')['body'] ?? null;
    }

    public function latestPanelVersion(): ?string
    {
        $tag = $this->latestRelease('panel')['tag_name'] ?? null;

        return $tag === null ? null : trim($tag, 'v');
    }

    public function latestWingsVersion(): ?string
    {
        $tag = $this->latestRelease('wings')['tag_name'] ?? null;

        return $tag === null ? null : trim($tag, 'v');
    }

    /**
     * Failures are cached briefly so a GitHub outage costs one timeout every few minutes instead of one per call.
     *
     * @return ?array<string, mixed>
     */
    private function latestRelease(string $repo): ?array
    {
        $key = "$repo:latest_release";
        $release = cache()->get($key);

        if ($release === null) {
            try {
                $release = Http::timeout(5)->connectTimeout(1)->get("https://api.github.com/repos/pelican/$repo/releases/latest")->throw()->json();
                cache()->put($key, $release, now()->addMinutes(config('panel.cdn.cache_time', 60)));
            } catch (Exception) {
                $release = 'error';
                cache()->put($key, $release, now()->addMinutes(5));
            }
        }

        return is_array($release) ? $release : null;
    }

    /**
     * Download urls of a panel release's assets, keyed by asset name. Not cached, only the updater needs this.
     *
     * @return array<string, string>
     *
     * @throws Exception
     */
    public function panelReleaseAssets(string $version): array
    {
        foreach (["v$version", $version] as $tag) {
            $response = Http::timeout(10)->connectTimeout(5)->get('https://api.github.com/repos/pelican/panel/releases/tags/' . rawurlencode($tag));

            if ($response->notFound()) {
                continue;
            }

            return collect($response->throw()->json('assets', []))
                ->mapWithKeys(fn (array $asset) => [$asset['name'] => $asset['browser_download_url']])
                ->all();
        }

        throw new Exception("Release $version was not found on GitHub.");
    }

    public function isLatestPanel(): bool
    {
        $current = $this->currentComparableVersion();
        $latest = $this->latestPanelVersion();

        return $current === null || $latest === null || version_compare($current, $latest, '>=');
    }

    public function isLatestWings(string $version): bool
    {
        $latest = $this->latestWingsVersion();

        return $version === 'develop' || $latest === null || version_compare($version, $latest, '>=');
    }

    public function isCompatibleWings(string $version): bool
    {
        return $version === 'develop' || version_compare($version, config('panel.wings.min_version'), '>=');
    }

    /**
     * The version for display purposes, e.g. "1.2.3" or "canary (0a1b2c3)". Never use this for comparisons.
     */
    public function currentPanelVersion(): string
    {
        return cache()->remember('panel:current_version', now()->addMinutes(5), function () {
            $version = config('app.version');

            if ($version !== 'canary') {
                return $version;
            }

            if (file_exists(base_path('.git/HEAD'))) {
                $head = explode(' ', file_get_contents(base_path('.git/HEAD')));

                if (array_key_exists(1, $head)) {
                    $path = base_path('.git/' . trim($head[1]));

                    if (file_exists($path)) {
                        return 'canary (' . substr(file_get_contents($path), 0, 7) . ')';
                    }
                }
            }

            return $version;
        });
    }

    /**
     * The comparable semver of this installation, or null when running canary.
     */
    public function currentComparableVersion(): ?string
    {
        $version = config('app.version');

        return $version === 'canary' ? null : $version;
    }
}
