<?php

namespace App\Repositories\Daemon;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\UploadedFile;

/**
 * Manages the plugins installed on a node's Wings daemon.
 *
 * Wings plugins are a separate population from the Panel's own: they are Go
 * source living on the node, they hook things only the daemon can see, and they
 * are enabled per node rather than per installation. What they share is the
 * shape of a plugin, so the responses this reads back use the same field names
 * as the Panel's plugin resource and the admin UI can present both the same way.
 */
class DaemonPluginRepository extends DaemonRepository
{
    /**
     * List every plugin on the node.
     *
     * A node whose Wings has the plugin subsystem switched off answers with
     * `enabled: false` rather than an error, so the UI can tell "this node runs
     * no plugins" apart from "this node could not be reached".
     *
     * @return array{enabled: bool, directory: ?string, data: array<int, array<string, mixed>>, supported: bool}
     *
     * @throws ConnectionException
     */
    public function getPlugins(): array
    {
        try {
            $response = $this->getHttpClient()
                ->connectTimeout(3)
                ->timeout(15)
                ->get('/api/plugins');
        } catch (RequestException $exception) {
            // A node running a Wings from before plugins existed has no such
            // route and answers 404. That is not a failure worth reporting as
            // one: the node is healthy, it just has nothing to say here.
            // Everything else is a real problem and keeps propagating.
            //
            // The check has to happen here rather than with throwUnlessStatus,
            // because the shared daemon client already throws on any non-2xx
            // in order to verify the node token.
            if ($exception->response->notFound()) {
                return ['enabled' => false, 'directory' => null, 'data' => [], 'supported' => false];
            }

            throw $exception;
        }

        $body = $response->json();

        return [
            'enabled' => (bool) ($body['enabled'] ?? false),
            'directory' => $body['directory'] ?? null,
            'data' => $body['data'] ?? [],
            'supported' => true,
        ];
    }

    /**
     * Return a single plugin.
     *
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function getPlugin(string $plugin): array
    {
        return $this->getHttpClient()
            ->timeout(15)
            ->get("/api/plugins/$plugin")
            ->json();
    }

    /**
     * Install a plugin whose files are on the node but which has never been set
     * up. Wings proves the plugin loads before accepting it, so a failure here
     * means the plugin does not work rather than that the request was malformed.
     *
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function install(string $plugin, bool $enable = true): array
    {
        return $this->getHttpClient()
            ->timeout(60)
            ->post("/api/plugins/$plugin/install", [])
            ->json() ?? [];
    }

    /**
     * Enable a plugin. This takes effect immediately on the node; Wings is not
     * restarted.
     *
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function enable(string $plugin): array
    {
        return $this->getHttpClient()
            ->timeout(60)
            ->post("/api/plugins/$plugin/enable")
            ->json() ?? [];
    }

    /**
     * Disable a plugin, unloading it and withdrawing its routes, jobs, parsers
     * and backup adapter.
     *
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function disable(string $plugin): array
    {
        return $this->getHttpClient()
            ->timeout(60)
            ->post("/api/plugins/$plugin/disable")
            ->json() ?? [];
    }

    /**
     * Uninstall a plugin, optionally deleting its files from the node.
     *
     * Keeping the files is the default, matching the Panel's own uninstall, so
     * that an operator can put a plugin back without fetching it again.
     *
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function uninstall(string $plugin, bool $deleteFiles = false): array
    {
        return $this->getHttpClient()
            ->timeout(60)
            ->post("/api/plugins/$plugin/uninstall?delete=" . ($deleteFiles ? 'true' : 'false'))
            ->json() ?? [];
    }

    /**
     * Download and install a plugin's newest version from its update url.
     *
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function update(string $plugin): array
    {
        return $this->getHttpClient()
            ->timeout(300)
            ->post("/api/plugins/$plugin/update")
            ->json() ?? [];
    }

    /**
     * Save an operator's settings for a plugin.
     *
     * Only keys the plugin's manifest declares are accepted; Wings rejects
     * anything else rather than writing a value nothing reads.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function saveSettings(string $plugin, array $values): array
    {
        return $this->getHttpClient()
            ->timeout(30)
            ->put("/api/plugins/$plugin/settings", $values)
            ->json() ?? [];
    }

    /**
     * Set the order plugins load in, which is the order their hooks run and
     * therefore which plugin gets to refuse an action first.
     *
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
     * Import a plugin onto the node from a url.
     *
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
     * Import a plugin onto the node from an uploaded archive.
     *
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
     * List the files making up a plugin, or read one of them.
     *
     * A Wings plugin ships as readable source so that an operator can see what
     * it does before enabling it. Reading it through the Panel is what makes
     * that practical, rather than requiring shell access to every node.
     *
     * @return array{files?: string[], file?: string, content?: string}
     *
     * @throws ConnectionException
     */
    public function getSource(string $plugin, ?string $file = null): array
    {
        return $this->getHttpClient()
            ->timeout(15)
            ->get("/api/plugins/$plugin/source", $file ? ['file' => $file] : [])
            ->json() ?? [];
    }
}
