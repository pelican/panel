<?php

namespace App\Repositories\Daemon;

use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Promises\LazyPromise;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Throwable;

/**
 * The Wings plugin API (pelican/wings contract v3, section A).
 */
class DaemonPluginRepository extends DaemonRepository
{
    /** Panel access levels a node can have, lowest first (contract L). */
    public const ACCESS_LEVELS = ['off', 'view', 'signed', 'full'];

    /** Actions that install code in two steps (contract A4). */
    public const STAGED_ACTIONS = ['install', 'update', 'import'];

    /** A registry namespace, as the admin configures it on the node (contract R3). */
    public const REGISTRY_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]*$/';

    /**
     * Whether $id can be a plugin name: not empty, not starting with ".", and without "/", "\" or "..".
     * Wings refuses anything else, and the Panel must not build a URL with it (contract A1).
     */
    public static function isValidId(mixed $id): bool
    {
        return is_string($id)
            && $id !== ''
            && !str_starts_with($id, '.')
            && !str_contains($id, '/')
            && !str_contains($id, '\\')
            && !str_contains($id, '..')
            && !preg_match('/[\x00-\x1F\x7F]/', $id);
    }

    public static function isValidLevel(mixed $level): bool
    {
        return is_string($level) && in_array($level, self::ACCESS_LEVELS, true);
    }

    /** Whether a node at $level allows what needs $required. */
    public static function levelAllows(?string $level, string $required): bool
    {
        if (!self::isValidLevel($level)) {
            return false;
        }

        return array_search($level, self::ACCESS_LEVELS, true) >= array_search($required, self::ACCESS_LEVELS, true);
    }

    /**
     * List every plugin on the node, or why the node can't list them.
     *
     * @return array{available: bool, reason: ?string, panel_access: ?string, required: ?string, directory: ?string, data: array<int, mixed>, error: ?string}
     *
     * @throws ConnectionException
     */
    public function getPlugins(): array
    {
        return $this->getPluginsAsync()->wait();
    }

    /**
     * Same as getPlugins() but doesn't wait, so several nodes can be asked at once.
     *
     * A 403 (the node's access level) and a 404 (a Wings without the plugin API) are answers, not
     * failures: the promise resolves with available=false and the reason (contract A1, A7). Only this
     * call reads a 404 as "no plugin API"; on a /api/plugins/{id} path it means "no such plugin".
     */
    public function getPluginsAsync(): PromiseInterface
    {
        return self::built($this->getHttpClient()
            ->connectTimeout(3)
            ->timeout(15)
            ->async()
            ->get('/api/plugins'))
            ->then(function (Response|Throwable $response) {
                if ($response instanceof RequestException && self::isFromWings($response->response)) {
                    if ($response->response->notFound()) {
                        return self::unavailable('unsupported');
                    }

                    if ($response->response->forbidden()) {
                        $level = $response->response->json('panel_access');
                        $required = $response->response->json('required');

                        return self::unavailable(
                            'access',
                            self::isValidLevel($level) ? $level : 'off',
                            self::isValidLevel($required) ? $required : 'view',
                        );
                    }
                }

                if ($response instanceof Throwable) {
                    throw $response;
                }

                $body = $response->json();
                if (!is_array($body)) {
                    throw new InvalidArgumentException('The node answered /api/plugins with something other than a JSON object.');
                }

                $level = $body['panel_access'] ?? null;

                return [
                    'available' => true,
                    'reason' => null,
                    // Contract v3 always sends it. Only defensively, a list without a known level is read as
                    // the lowest level that can list, so nothing beyond viewing is offered
                    'panel_access' => self::isValidLevel($level) ? $level : 'view',
                    'required' => null,
                    // "enabled" is reserved and always true (A2), so it isn't read
                    'directory' => is_string($body['directory'] ?? null) ? $body['directory'] : null,
                    'data' => is_array($body['data'] ?? null) ? array_values($body['data']) : [],
                    'error' => is_string($body['error'] ?? null) && $body['error'] !== '' ? $body['error'] : null,
                ];
            });
    }

    /**
     * The request behind an async call, sent now. Laravel's LazyPromise sends nothing until it is waited on,
     * so promises settled together would be asked one after another, and its then() queues the callback and
     * hands back the lazy promise itself rather than the transformed one.
     */
    private static function built(PromiseInterface $promise): PromiseInterface
    {
        return $promise instanceof LazyPromise && $promise->promiseNeedsBuilt() ? $promise->buildPromise() : $promise;
    }

    /** @return array{available: false, reason: string, panel_access: ?string, required: ?string, directory: null, data: array{}, error: null} */
    private static function unavailable(string $reason, ?string $level = null, ?string $required = null): array
    {
        return [
            'available' => false,
            'reason' => $reason,
            'panel_access' => $level,
            'required' => $required,
            'directory' => null,
            'data' => [],
            'error' => null,
        ];
    }

    /** Only an answer from Wings itself says something about its plugin API; a proxy's 403/404 doesn't. */
    private static function isFromWings(Response $response): bool
    {
        return str_starts_with((string) $response->header('User-Agent'), 'Pelican Wings/');
    }

    /**
     * What the node's registries offer for Wings (A2, A3.2). A registry the node couldn't read is in
     * "errors", keyed by its namespace; it doesn't fail the call. Entries that aren't well formed are left out.
     *
     * @return array{data: list<array{name: string, version: string, registry: string, author: ?string, description: ?string, capability: ?string, has_signature: bool, installed: ?string}>, errors: array<string, string>}
     *
     * @throws ConnectionException
     */
    public function available(): array
    {
        $body = $this->getHttpClient()
            ->timeout(60)
            ->get('/api/plugins/available')
            ->json();

        if (!is_array($body)) {
            throw new InvalidArgumentException('The node answered /api/plugins/available with something other than a JSON object.');
        }

        $text = fn (array $from, string $key): ?string => is_string($from[$key] ?? null) && $from[$key] !== '' ? $from[$key] : null;

        $data = [];
        foreach (is_array($body['data'] ?? null) ? $body['data'] : [] as $entry) {
            if (!is_array($entry) || !self::isValidId($entry['name'] ?? null) || !is_string($entry['registry'] ?? null) || !preg_match(self::REGISTRY_PATTERN, $entry['registry'])) {
                continue;
            }

            $data[] = [
                'name' => $entry['name'],
                'version' => $text($entry, 'version') ?? '',
                'registry' => $entry['registry'],
                'author' => $text($entry, 'author'),
                'description' => $text($entry, 'description'),
                'capability' => $text($entry, 'capability'),
                'has_signature' => ($entry['has_signature'] ?? false) === true,
                'installed' => $text($entry, 'installed'),
            ];
        }

        $errors = [];
        foreach (is_array($body['errors'] ?? null) ? $body['errors'] : [] as $namespace => $message) {
            if (is_string($namespace) && is_string($message)) {
                $errors[$namespace] = $message;
            }
        }

        return ['data' => $data, 'errors' => $errors];
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
            ->post($this->path($plugin, 'enable'), [])
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
            ->post($this->path($plugin, 'disable'), [])
            ->json() ?? [];
    }

    /**
     * Disables the plugin and revokes its approval; $deleteFiles also removes its directory.
     *
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function uninstall(string $plugin, bool $deleteFiles = false): array
    {
        return $this->getHttpClient()
            ->timeout(60)
            ->post($this->path($plugin, 'uninstall') . '?delete=' . ($deleteFiles ? 'true' : 'false'), [])
            ->json() ?? [];
    }

    /**
     * Replaces all of the plugin's setting values (contract A3.1).
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
            ->put($this->path($plugin, 'settings'), $values)
            ->json() ?? [];
    }

    /**
     * First step of an install from the node's registries: Wings stages it and answers 202 with an approval token.
     *
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function install(string $plugin, ?string $registry = null): array
    {
        $body = $registry !== null && $registry !== '' ? ['registry' => $registry] : [];

        return $this->staged($this->path($plugin, 'install'), $body);
    }

    /**
     * First step of an update from the plugin's update URL: 202 with an approval token, or "up_to_date".
     *
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function update(string $plugin): array
    {
        return $this->staged($this->path($plugin, 'update'), []);
    }

    /**
     * First step of an import from a URL (needs "full").
     *
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function importFromUrl(string $url, ?string $sha256 = null, ?string $signature = null): array
    {
        return $this->staged('/api/plugins/import', array_filter([
            'url' => $url,
            'sha256' => $sha256,
            'signature' => $signature,
        ], fn ($value) => $value !== null && $value !== ''));
    }

    /**
     * First step of an import from an uploaded archive (needs "full").
     *
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function importFromFile(UploadedFile $file, ?string $signature = null): array
    {
        $elements = [[
            'name' => 'plugin',
            // Streamed from disk, archives can be up to 200 MiB
            'contents' => Utils::tryFopen($file->getRealPath(), 'r'),
            'filename' => $file->getClientOriginalName(),
        ]];

        if ($signature !== null && $signature !== '') {
            $elements[] = ['name' => 'signature', 'contents' => $signature];
        }

        // The daemon client sends "Content-Type: application/json", which attach() keeps, so Wings would
        // read the upload as JSON. The body and its type are set here instead.
        $boundary = bin2hex(random_bytes(16));

        $response = $this->getHttpClient()
            ->timeout(300)
            ->withBody(new MultipartStream($elements, $boundary), "multipart/form-data; boundary=$boundary")
            ->post('/api/plugins/import');

        return self::stagedResponse($response);
    }

    /**
     * Second step: send the approval token back to the endpoint that staged the plugin.
     * $plugin is null for an import, which has no id in its path.
     *
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    public function approve(string $action, ?string $plugin, string $token): array
    {
        if (!in_array($action, self::STAGED_ACTIONS, true)) {
            throw new InvalidArgumentException("Unknown plugin action \"$action\".");
        }

        if ($token === '') {
            throw new InvalidArgumentException('The approval token is empty.');
        }

        $path = $action === 'import' ? '/api/plugins/import' : $this->path((string) $plugin, $action);

        return $this->getHttpClient()
            ->timeout(300)
            ->post($path, ['approve' => $token])
            ->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    private function staged(string $path, array $body): array
    {
        return self::stagedResponse(
            $this->getHttpClient()
                ->timeout(300)
                ->post($path, $body)
        );
    }

    /** @return array<string, mixed> */
    private static function stagedResponse(Response $response): array
    {
        $body = $response->json();

        if (!is_array($body) || !is_string($body['status'] ?? null)) {
            throw new InvalidArgumentException('The node did not say what it staged.');
        }

        if ($body['status'] === 'approval_required' && (!is_string($body['approval_token'] ?? null) || $body['approval_token'] === '')) {
            throw new InvalidArgumentException('The node staged the plugin but sent no approval token.');
        }

        return $body;
    }

    /**
     * @return array{files?: string[], truncated?: bool, file?: string, size?: int, binary?: bool, content?: string}
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
     * Ids are checked and encoded before going into a path, so /api/plugins//uninstall can't be built.
     */
    private function path(string $plugin, ?string $action = null): string
    {
        if (!self::isValidId($plugin)) {
            throw new InvalidArgumentException('Refusing to call Wings with an empty or invalid plugin id.');
        }

        return '/api/plugins/' . rawurlencode($plugin) . ($action ? "/$action" : '');
    }
}
