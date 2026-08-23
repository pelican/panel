<?php

namespace App\Services\Helpers;

use Exception;
use Illuminate\Support\Facades\Http;

class SoftwareVersionService
{
    public function latestPanelVersionChangelog(): string
    {
        $key = 'panel:latest_version_changelog';
        if (cache()->get($key) === 'error') {
            cache()->forget($key);
        }

        return cache()->remember($key, now()->addMinutes(config('panel.cdn.cache_time', 60)), function () {
            try {
                $response = Http::timeout(5)->connectTimeout(1)->get('https://api.github.com/repos/pelican/panel/releases/latest')->throw()->json();

                return $response['body'];
            } catch (Exception) {
                return 'error';
            }
        });
    }

    public function latestPanelVersion(): string
    {
        $key = 'panel:latest_version';
        if (cache()->get($key) === 'error') {
            cache()->forget($key);
        }

        return cache()->remember($key, now()->addMinutes(config('panel.cdn.cache_time', 60)), function () {
            try {
                $response = Http::timeout(5)->connectTimeout(1)->get('https://api.github.com/repos/pelican/panel/releases/latest')->throw()->json();

                return trim($response['tag_name'], 'v');
            } catch (Exception) {
                return 'error';
            }
        });
    }

    public function latestWingsVersion(): string
    {
        $key = 'wings:latest_version';
        if (cache()->get($key) === 'error') {
            cache()->forget($key);
        }

        return cache()->remember($key, now()->addMinutes(config('panel.cdn.cache_time', 60)), function () {
            try {
                $response = Http::timeout(5)->connectTimeout(1)->get('https://api.github.com/repos/pelican/wings/releases/latest')->throw()->json();

                return trim($response['tag_name'], 'v');
            } catch (Exception) {
                return 'error';
            }
        });
    }

    public function isLatestPanel(): bool
    {
        if (config('app.version') === 'canary') {
            return true;
        }

        return version_compare(config('app.version'), $this->latestPanelVersion()) >= 0;
    }

    public function isLatestWings(string $version): bool
    {
        if ($version === 'develop') {
            return true;
        }

        return version_compare($version, $this->latestWingsVersion()) >= 0;
    }

    public function currentPanelVersion(): string
    {
        return cache()->remember('panel:current_version', now()->addMinutes(5), function () {
            if (file_exists(base_path('.git/HEAD'))) {
                $head = explode(' ', file_get_contents(base_path('.git/HEAD')));

                if (array_key_exists(1, $head)) {
                    $path = base_path('.git/' . trim($head[1]));

                    if (file_exists($path)) {
                        return 'canary (' . substr(file_get_contents($path), 0, 7) . ')';
                    }
                }
            }

            return config('app.version');
        });
    }
}
