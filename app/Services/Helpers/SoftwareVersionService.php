<?php

namespace App\Services\Helpers;

use Exception;
use Illuminate\Support\Facades\Http;

class SoftwareVersionService
{
    public function latestPanelVersionChangelog(): ?string
    {
        $key = 'panel:latest_version_changelog';
        if (cache()->get($key) === 'error') {
            cache()->forget($key);
        }

        $changelog = cache()->remember($key, now()->addMinutes(config('panel.cdn.cache_time', 60)), function () {
            try {
                $response = Http::timeout(5)->connectTimeout(1)->get('https://api.github.com/repos/pelican/panel/releases/latest')->throw()->json();

                return $response['body'];
            } catch (Exception) {
                return 'error';
            }
        });

        return $changelog === 'error' ? null : $changelog;
    }

    public function latestPanelVersion(): ?string
    {
        $key = 'panel:latest_version';
        if (cache()->get($key) === 'error') {
            cache()->forget($key);
        }

        $version = cache()->remember($key, now()->addMinutes(config('panel.cdn.cache_time', 60)), function () {
            try {
                $response = Http::timeout(5)->connectTimeout(1)->get('https://api.github.com/repos/pelican/panel/releases/latest')->throw()->json();

                return trim($response['tag_name'], 'v');
            } catch (Exception) {
                return 'error';
            }
        });

        return $version === 'error' ? null : $version;
    }

    public function latestWingsVersion(): ?string
    {
        $key = 'wings:latest_version';
        if (cache()->get($key) === 'error') {
            cache()->forget($key);
        }

        $version = cache()->remember($key, now()->addMinutes(config('panel.cdn.cache_time', 60)), function () {
            try {
                $response = Http::timeout(5)->connectTimeout(1)->get('https://api.github.com/repos/pelican/wings/releases/latest')->throw()->json();

                return trim($response['tag_name'], 'v');
            } catch (Exception) {
                return 'error';
            }
        });

        return $version === 'error' ? null : $version;
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
