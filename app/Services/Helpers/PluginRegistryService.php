<?php

namespace App\Services\Helpers;

use GuzzleHttp\Exception\ConnectTimeoutException;
use GuzzleHttp\Exception\NetworkTimeoutException;
use GuzzleHttp\Exception\ResponseTimeoutException;
use GuzzleHttp\Exception\TransferException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Psr7\Exception\TimeoutException;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use GuzzleHttp\RequestOptions;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;

/**
 * Finds Panel plugins in plugin registries: pelican/wings plugin contract v3, sections R1 to R3.
 *
 * A registry is one https JSON document `{"name", "plugins": [...]}`. The Panel only uses entries with
 * `target: "panel"`; a missing target means "wings", so registries written before targets existed only
 * ever hold Wings plugins. Registries are configured in PANEL_PLUGIN_REGISTRIES (see PluginRegistryConfig).
 *
 * A plugin is asked for as:
 *  - `@namespace/name`: that configured registry only, with its credentials;
 *  - `https://host/registry.json#name`: that URL only, never with credentials;
 *  - `name`: every configured registry. It must be listed (for the Panel) by exactly one of them, and every
 *    one of them must be readable, so a public registry can't take over a name a private one uses.
 */
class PluginRegistryService
{
    public const MAX_DOCUMENT_BYTES = 1024 * 1024;

    public const MAX_REDIRECTS = 10;

    /** What BoundedStreamFactory and bounded() refuse a body over the cap with; see refusedAsTooLarge(). */
    public const TOO_LARGE = 'Response too large.';

    /** Whether any registry is configured. A panel without one keeps behaving exactly as before. */
    public function hasRegistries(): bool
    {
        return config('panel.plugin.registries') !== [] && config('panel.plugin.registries') !== null;
    }

    /** Whether the reference can only mean a registry plugin (`@namespace/name` or `url#name`), never a local plugin id. */
    public function isReference(string $reference): bool
    {
        return str_starts_with($reference, '@') || str_contains($reference, '#');
    }

    /**
     * The configured registries by namespace, sorted by namespace.
     *
     * @return array<string, array{url: string, token: ?string, headers: array<string, string>, error: ?string}>
     *
     * @throws RuntimeException when PANEL_PLUGIN_REGISTRIES can't be read at all
     */
    public function registries(): array
    {
        $registries = config('panel.plugin.registries') ?? [];

        if (is_string($registries)) {
            throw new RuntimeException($registries);
        }

        ksort($registries);

        return $registries;
    }

    /** @throws RuntimeException */
    public function resolve(string $reference): RegistryPlugin
    {
        $reference = trim($reference);

        if (str_contains($reference, '#')) {
            [$url, $name] = explode('#', $reference, 2);

            return $this->find($this->readDocument($url, null), $name, null, $url)
                ?? throw new RuntimeException(trans('admin/plugin.registry.not_found_in', ['name' => $name, 'registry' => $url]));
        }

        if (str_starts_with($reference, '@')) {
            [$namespace, $name] = array_pad(explode('/', substr($reference, 1), 2), 2, '');

            if ($namespace === '' || $name === '') {
                throw new RuntimeException(trans('admin/plugin.registry.invalid_reference', ['reference' => $reference]));
            }

            $registry = $this->registries()[$namespace] ?? throw new RuntimeException(trans('admin/plugin.registry.unknown_namespace', ['namespace' => $namespace]));

            return $this->find($this->readRegistry($namespace, $registry), $name, $namespace, $registry['url'])
                ?? throw new RuntimeException(trans('admin/plugin.registry.not_found_in', ['name' => $name, 'registry' => "@$namespace"]));
        }

        return $this->resolveBareName($reference);
    }

    /**
     * Downloads a registry URL or an archive: https on every hop, at most MAX_REDIRECTS redirects, and the
     * namespace's credentials only on requests to that registry's own origin (scheme, host and port). Once a
     * redirect leaves that origin the credentials are gone for the rest of the chain. $timeout covers the whole
     * chain: every request (connecting, the headers and the body) gets only what is left of it.
     *
     * @throws RuntimeException
     */
    public function fetch(string $url, ?string $namespace, int $maxBytes, int $timeout = 15): string
    {
        $credentials = [];
        $credentialOrigin = null;

        if ($namespace !== null) {
            $registry = $this->registries()[$namespace] ?? throw new RuntimeException(trans('admin/plugin.registry.unknown_namespace', ['namespace' => $namespace]));

            if ($registry['error'] !== null) {
                throw new RuntimeException(trans('admin/plugin.registry.misconfigured', ['namespace' => $namespace, 'error' => $registry['error']]));
            }

            $credentials = $registry['headers'];
            if ($registry['token'] !== null && $registry['token'] !== '') {
                $credentials['Authorization'] = 'Bearer ' . $registry['token'];
            }
            $credentialOrigin = self::origin($registry['url']);
        }

        $deadline = self::deadline($timeout);

        for ($redirects = 0; ; $redirects++) {
            $origin = self::origin($url) ?? throw new RuntimeException(trans('admin/plugin.registry.https_only', ['url' => $url]));

            if ($origin !== $credentialOrigin) {
                $credentials = [];
            }

            self::assertBefore($deadline, $url);

            try {
                $response = self::bounded(Http::withHeaders($credentials)->withoutRedirecting(), $maxBytes, $deadline)->get($url);
            } catch (ConnectionException|TransferException $exception) {
                if (self::refusedAsTooLarge($exception)) {
                    throw new RuntimeException(trans('admin/plugin.registry.too_large', ['url' => $url]), previous: $exception);
                }

                if (self::timedOutTransfer($exception)) {
                    throw self::timedOut($url, $exception);
                }

                throw new RuntimeException(trans('admin/plugin.registry.fetch_failed', ['url' => $url, 'error' => $exception->getMessage()]), previous: $exception);
            }

            if ($response->redirect()) {
                if ($redirects >= self::MAX_REDIRECTS) {
                    throw new RuntimeException(trans('admin/plugin.registry.too_many_redirects', ['url' => $url]));
                }

                $location = $response->header('Location');
                if ($location === '') {
                    throw new RuntimeException(trans('admin/plugin.registry.fetch_failed', ['url' => $url, 'error' => 'redirect without a Location']));
                }

                $url = (string) UriResolver::resolve(new Uri($url), new Uri($location));

                continue;
            }

            try {
                $response->throw();
            } catch (RequestException $exception) {
                throw new RuntimeException(trans('admin/plugin.registry.fetch_failed', ['url' => $url, 'error' => 'HTTP ' . $exception->response->status()]), previous: $exception);
            }

            return self::readBody($response, $maxBytes, $deadline, $url)
                ?? throw new RuntimeException(trans('admin/plugin.registry.too_large', ['url' => $url]));
        }
    }

    /** The clock of every download deadline, in seconds; the test clock when time is frozen in tests. */
    public static function now(): float
    {
        return (float) now()->format('U.u');
    }

    /** The deadline of a download that may take $seconds from now, for bounded() and readBody(). */
    public static function deadline(float $seconds): float
    {
        return self::now() + $seconds;
    }

    /**
     * $request set up for a download whose body is read with readBody(), due by $deadline (see deadline()).
     *
     * It runs on curl, whose timeout covers the whole request: connecting, the TLS handshake, the headers and the
     * body all have to be done by the deadline. (Guzzle sends `stream` requests to its stream handler instead,
     * where waiting for the headers is only bounded per read, so a server sending a byte now and then could hold
     * a request open well past the deadline.) A response that announces more than $maxBytes is refused from its
     * headers, and one without a length as soon as more than $maxBytes of it arrive (R2.4).
     */
    public static function bounded(PendingRequest $request, int $maxBytes, float $deadline): PendingRequest
    {
        // Guzzle refuses a timeout under a millisecond, and 0 would mean none at all.
        $remaining = max($deadline - self::now(), 0.001);

        return $request
            ->setHandler(new CurlHandler())
            ->timeout($remaining)
            ->connectTimeout(min(5, $remaining))
            ->withOptions([
                RequestOptions::ON_HEADERS => function (ResponseInterface $response) use ($maxBytes) {
                    if ((int) $response->getHeaderLine('Content-Length') > $maxBytes) {
                        throw new RuntimeException(self::TOO_LARGE);
                    }
                },
                RequestOptions::STREAM_FACTORY => new BoundedStreamFactory($maxBytes),
            ]);
    }

    /** Whether a failed request was refused by bounded() because its body was over the cap. */
    public static function refusedAsTooLarge(Throwable $exception): bool
    {
        for ($cause = $exception; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof RuntimeException && $cause->getMessage() === self::TOO_LARGE) {
                return true;
            }
        }

        return false;
    }

    /** Whether a failed request ran out of time: connecting, waiting for the headers or reading the body. */
    private static function timedOutTransfer(Throwable $exception): bool
    {
        for ($cause = $exception; $cause !== null; $cause = $cause->getPrevious()) {
            if ($cause instanceof ConnectTimeoutException || $cause instanceof NetworkTimeoutException || $cause instanceof ResponseTimeoutException) {
                return true;
            }
        }

        return false;
    }

    /**
     * The body of a response requested with bounded(), read in chunks so that no more than $maxBytes + 1
     * bytes are ever read, whatever the server says or doesn't say about its length (R2.4). Null when the body
     * is larger than $maxBytes.
     *
     * @throws RuntimeException when $deadline (see deadline()) passes before the whole body is read
     */
    public static function readBody(Response $response, int $maxBytes, float $deadline, ?string $url = null): ?string
    {
        $url ??= (string) $response->effectiveUri();
        $stream = $response->toPsrResponse()->getBody();

        if ((int) $response->header('Content-Length') > $maxBytes) {
            $stream->close();

            return null;
        }

        // A network body isn't seekable; one that is (a buffered or faked response) is read from its start.
        if ($stream->isSeekable()) {
            $stream->rewind();
        }

        $body = '';

        while (!$stream->eof()) {
            try {
                // Checked after the read as well: a read can block, and a body that arrives late is still late.
                self::assertBefore($deadline, $url);
                $chunk = $stream->read(min(64 * 1024, $maxBytes - strlen($body) + 1));
                self::assertBefore($deadline, $url);
            } catch (RuntimeException $exception) {
                $stream->close();

                throw $exception instanceof TimeoutException ? self::timedOut($url) : $exception;
            }

            if ($chunk === '') {
                break;
            }

            $body .= $chunk;

            if (strlen($body) > $maxBytes) {
                // Stop the download here instead of leaving the rest of it to arrive.
                $stream->close();

                return null;
            }
        }

        return $body;
    }

    /** @throws RuntimeException */
    private static function assertBefore(float $deadline, string $url): void
    {
        // Guzzle can't wait less than a millisecond, see bounded().
        if ($deadline - self::now() < 0.001) {
            throw self::timedOut($url);
        }
    }

    private static function timedOut(string $url, ?Throwable $previous = null): RuntimeException
    {
        return new RuntimeException(trans('admin/plugin.registry.fetch_failed', ['url' => $url, 'error' => 'timed out']), previous: $previous);
    }

    /**
     * The `target: "panel"` entries of a registry document, as raw arrays with a string `name` (R1).
     * Unknown fields are kept and ignored; entries for Wings, including those without a `target`, are dropped.
     *
     * @param  array<mixed>  $document
     * @return array<int, array<string, mixed>>
     */
    public function panelEntries(array $document): array
    {
        return collect($document['plugins'])
            ->filter(fn ($entry) => is_array($entry) && is_string($entry['name'] ?? null) && ($entry['target'] ?? 'wings') === 'panel')
            ->values()
            ->all();
    }

    /**
     * Fetches and decodes a registry document, refusing anything that isn't one (R1).
     *
     * @return array<mixed>
     *
     * @throws RuntimeException
     */
    public function readDocument(string $url, ?string $namespace): array
    {
        $body = $this->fetch($url, $namespace, self::MAX_DOCUMENT_BYTES);

        try {
            $document = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException(trans('admin/plugin.registry.invalid_document', ['url' => $url]), previous: $exception);
        }

        // `plugins` must be an array; an empty one is valid, `null` or a missing one is not.
        if (!is_array($document) || !is_array($document['plugins'] ?? null) || !array_is_list($document['plugins'])) {
            throw new RuntimeException(trans('admin/plugin.registry.invalid_document', ['url' => $url]));
        }

        return $document;
    }

    /**
     * @param  array{url: string, token: ?string, headers: array<string, string>, error: ?string}  $registry
     * @return array<mixed>
     *
     * @throws RuntimeException
     */
    private function readRegistry(string $namespace, array $registry): array
    {
        if ($registry['error'] !== null) {
            throw new RuntimeException(trans('admin/plugin.registry.misconfigured', ['namespace' => $namespace, 'error' => $registry['error']]));
        }

        return $this->readDocument($registry['url'], $namespace);
    }

    /** @throws RuntimeException */
    private function resolveBareName(string $name): RegistryPlugin
    {
        $registries = $this->registries();

        if ($registries === []) {
            throw new RuntimeException(trans('admin/plugin.registry.none_configured'));
        }

        $found = [];

        foreach ($registries as $namespace => $registry) {
            // PHP stores a numeric namespace like "0" as an integer key.
            $namespace = (string) $namespace;

            try {
                $document = $this->readRegistry($namespace, $registry);
            } catch (RuntimeException $exception) {
                // A registry that can't be read might be the one that holds the plugin the admin meant.
                throw new RuntimeException(trans('admin/plugin.registry.unreadable', ['namespace' => $namespace, 'error' => $exception->getMessage()]), previous: $exception);
            }

            if ($plugin = $this->find($document, $name, $namespace, $registry['url'])) {
                $found[] = $plugin;
            }
        }

        return match (count($found)) {
            0 => throw new RuntimeException(trans('admin/plugin.registry.not_found', ['name' => $name])),
            1 => $found[0],
            default => throw new RuntimeException(trans('admin/plugin.registry.ambiguous', [
                'name' => $name,
                'choices' => implode(', ', array_map(fn (RegistryPlugin $plugin) => $plugin->reference(), $found)),
            ])),
        };
    }

    /**
     * The first Panel entry with exactly this name, validated, or null when the registry doesn't list it.
     *
     * @param  array<mixed>  $document
     *
     * @throws RuntimeException when the entry is listed but can't be installed safely
     */
    private function find(array $document, string $name, ?string $namespace, string $registryUrl): ?RegistryPlugin
    {
        $entry = collect($this->panelEntries($document))->first(fn (array $entry) => $entry['name'] === $name);

        if ($entry === null) {
            return null;
        }

        $invalid = fn (string $reason) => new RuntimeException(trans('admin/plugin.registry.invalid_entry', ['name' => $name, 'reason' => $reason]));

        // The name becomes the plugin's directory and must equal the id in its plugin.json (R1).
        if (preg_match('/\A[a-z0-9][a-z0-9._-]*\z/', $name) !== 1 || str_contains($name, '..')) {
            throw $invalid('name');
        }

        if (!is_string($entry['version'] ?? null) || trim($entry['version']) === '') {
            throw $invalid('version');
        }

        if (!is_string($entry['download'] ?? null) || self::origin($entry['download']) === null) {
            throw $invalid('download');
        }

        if (!is_string($entry['sha256'] ?? null) || preg_match('/\A[a-f0-9]{64}\z/i', $entry['sha256']) !== 1) {
            throw $invalid('sha256');
        }

        // A signature that is present must be a non-empty string; anything else is invalid, never "unsigned" (R1).
        if (array_key_exists('signature', $entry) && (!is_string($entry['signature']) || trim($entry['signature']) === '')) {
            throw $invalid('signature');
        }

        $optional = fn (string $key) => is_string($entry[$key] ?? null) && $entry[$key] !== '' ? $entry[$key] : null;

        return new RegistryPlugin(
            name: $name,
            version: $entry['version'],
            download: $entry['download'],
            sha256: strtolower($entry['sha256']),
            signature: $optional('signature'),
            author: $optional('author'),
            description: $optional('description'),
            panelVersion: $optional('panel_version'),
            namespace: $namespace,
            registryUrl: $registryUrl,
            registryName: is_string($document['name'] ?? null) && $document['name'] !== '' ? $document['name'] : null,
        );
    }

    /** Lowercased `https://host:port` of an https URL (default port 443), or null for anything that isn't https. */
    public static function origin(string $url): ?string
    {
        $parts = parse_url($url);

        if (!is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || blank($parts['host'] ?? null)) {
            return null;
        }

        return 'https://' . strtolower($parts['host']) . ':' . ($parts['port'] ?? 443);
    }
}
