<?php

namespace App\Repositories\Daemon;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Throwable;

class DaemonPluginRepository extends DaemonRepository
{
    /**
     * List every plugin on the node.
     *
     * @return array{enabled: bool, directory: ?string, data: array<int, array<string, mixed>>, supported: bool}
     *
     * @throws ConnectionException
     */
    public function getPlugins(): array
    {
        return $this->getPluginsAsync()->wait();
    }

    /**
     * Same as getPlugins() but doesn't wait, so several nodes can be asked at once.
     */
    public function getPluginsAsync(): PromiseInterface
    {
        return $this->getHttpClient()
            ->connectTimeout(3)
            ->timeout(15)
            ->async()
            ->get('/api/plugins')
            ->then(function (Response|Throwable $response) {
                // Older Wings versions have no plugin route, but only a 404 from Wings itself counts
                if ($response instanceof RequestException && $response->response->notFound() && str_starts_with((string) $response->response->header('User-Agent'), 'Pelican Wings/')) {
                    return ['enabled' => false, 'directory' => null, 'data' => [], 'supported' => false];
                }

                if ($response instanceof Throwable) {
                    throw $response;
                }

                $body = $response->json();

                return [
                    'enabled' => (bool) ($body['enabled'] ?? false),
                    'directory' => $body['directory'] ?? null,
                    'data' => $body['data'] ?? [],
                    'supported' => true,
                ];
            });
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function getPlugin(string $plugin): array
    {
        return $this->getHttpClient()
            ->timeout(15)
            ->get($this->path($plugin))
            ->json();
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function install(string $plugin): array
    {
        return $this->getHttpClient()
            ->timeout(60)
            ->post($this->path($plugin, 'install'), [])
            ->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function enable(string $plugin): array
    {
        return $this->getHttpClient()
            ->timeout(60)
            ->post($this->path($plugin, 'enable'))
            ->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function disable(string $plugin): array
    {
        return $this->getHttpClient()
            ->timeout(60)
            ->post($this->path($plugin, 'disable'))
            ->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function uninstall(string $plugin, bool $deleteFiles = false): array
    {
        return $this->getHttpClient()
            ->timeout(60)
            ->post($this->path($plugin, 'uninstall') . '?delete=' . ($deleteFiles ? 'true' : 'false'))
            ->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function update(string $plugin): array
    {
        return $this->getHttpClient()
            ->timeout(300)
            ->post($this->path($plugin, 'update'))
            ->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function saveSettings(string $plugin, array $values): array
    {
        return $this->getHttpClient()
            ->timeout(30)
            ->put($this->path($plugin, 'settings'), $values)
            ->json() ?? [];
    }

    /**
     * @param  string[]  $order
     *
     * @throws ConnectionException
     */
    public function setLoadOrder(array $order): void
    {
        $this->getHttpClient()
            ->timeout(30)
            ->post('/api/plugins/order', ['order' => array_values($order)]);
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function importFromUrl(string $url): array
    {
        return $this->getHttpClient()
            ->timeout(300)
            ->post('/api/plugins/import', ['url' => $url])
            ->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function importFromFile(UploadedFile $file): array
    {
        return $this->getHttpClient()
            ->timeout(300)
            ->attach('plugin', $file->get(), $file->getClientOriginalName())
            ->post('/api/plugins/import')
            ->json() ?? [];
    }

    /**
     * @return array{files?: string[], file?: string, content?: string}
     *
     * @throws ConnectionException
     */
    public function getSource(string $plugin, ?string $file = null): array
    {
        return $this->getHttpClient()
            ->timeout(15)
            ->get($this->path($plugin, 'source'), $file ? ['file' => $file] : [])
            ->json() ?? [];
    }

    /**
     * Plugin ids come from the node, so they are encoded before going into a path.
     */
    private function path(string $plugin, ?string $action = null): string
    {
        return '/api/plugins/' . rawurlencode($plugin) . ($action ? "/$action" : '');
    }
}
