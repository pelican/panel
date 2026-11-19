<?php

namespace App\Services\Backups;

use App\Extensions\BackupAdapter\BackupAdapterService;
use App\Extensions\BackupAdapter\Schemas\S3BackupSchema;
use App\Extensions\BackupAdapter\Schemas\WingsBackupSchema;
use Exception;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use Psr\Http\Message\ResponseInterface;

/**
 * Finds the .pelicanconfig for a restore source. Wings stores it as the first
 * entry in the archive, so when the Panel has no copy only the start of the
 * archive needs to be downloaded to read it.
 */
class BackupConfigResolver
{
    public const CONFIG_FILE = '.pelicanconfig';

    /** The most of an archive that is downloaded to find the configuration. */
    public const MAX_READ_BYTES = 256 * 1024;

    /** The largest configuration that is read. */
    public const MAX_CONFIG_BYTES = 64 * 1024;

    /** How long reading the start of an archive may take in total, in seconds. */
    public const READ_DEADLINE = 15;

    /** How many redirects of an administrator's link are followed. */
    public const MAX_REDIRECTS = 3;

    public function __construct(private readonly BackupAdapterService $backupAdapterService) {}

    /**
     * @return array<string, mixed>|null
     */
    public function resolve(RestoreSource $source): ?array
    {
        return match ($source->type) {
            RestoreSource::TYPE_BACKUP => $this->fromBackup($source),
            RestoreSource::TYPE_S3 => $this->fromS3($source),
            RestoreSource::TYPE_URL => $this->fromUrl((string) $source->url, external: true),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fromBackup(RestoreSource $source): ?array
    {
        $backup = $source->getBackup();
        if (!$backup) {
            return null;
        }

        if ($backup->pelican_config) {
            return $backup->pelican_config;
        }

        // Backups made before the configuration was kept by the Panel may still
        // have it in the archive.
        try {
            $schema = $this->backupAdapterService->get($backup->backupHost->schema);

            $url = match (true) {
                $schema instanceof S3BackupSchema => $schema->getObjectLink($backup->backupHost, $schema->objectKey($backup)),
                $schema instanceof WingsBackupSchema => $schema->getRestoreLink($backup, 5),
                default => null,
            };
        } catch (Exception) {
            return null;
        }

        return $url ? $this->fromUrl($url) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fromS3(RestoreSource $source): ?array
    {
        $backupHost = $source->getBackupHost();
        $schema = $backupHost ? $this->backupAdapterService->get($backupHost->schema) : null;
        if (!$schema instanceof S3BackupSchema || !$source->key) {
            return null;
        }

        return $schema->readConfig($backupHost, $source->key)
            ?? $this->fromUrl($schema->getObjectLink($backupHost, $source->key));
    }

    /**
     * Downloads the start of a gzipped tar archive and returns the
     * configuration if it is the first entry.
     *
     * Links entered by an administrator are external: they may not point at this
     * Panel's own network, are not redirected, and are connected to at the address
     * that was checked.
     *
     * @return array<string, mixed>|null
     */
    public function fromUrl(string $url, bool $external = false): ?array
    {
        $compressed = $external ? $this->readExternal($url) : $this->readInternal($url);

        return $compressed === null ? null : $this->fromArchiveStart($compressed);
    }

    /**
     * Whether a link only points at public addresses, so the Panel and its nodes
     * may fetch it.
     */
    public function isPublicUrl(string $url): bool
    {
        return $this->publicAddress($url) !== null;
    }

    /**
     * Reads the start of a link the Panel generated itself, like a node's download
     * link, which may be on a private network.
     */
    private function readInternal(string $url): ?string
    {
        $deadline = microtime(true) + self::READ_DEADLINE;

        try {
            $response = Http::timeout(10)->connectTimeout(5)
                ->withOptions(['stream' => true, 'read_timeout' => 5, 'allow_redirects' => ['max' => 3, 'protocols' => ['http', 'https']]])
                ->get($url);

            if (!$response->successful()) {
                return null;
            }

            $body = $response->toPsrResponse()->getBody();
            $compressed = '';
            while (!$body->eof() && strlen($compressed) < self::MAX_READ_BYTES && microtime(true) < $deadline) {
                $compressed .= $body->read(min(16384, self::MAX_READ_BYTES - strlen($compressed)));
            }
            $body->close();
        } catch (Exception) {
            return null;
        }

        // Past the deadline the partial start is still read, the configuration may be in it.
        return $compressed;
    }

    /**
     * Reads the start of an administrator's link over cURL, the only Guzzle handler
     * that can connect to the address that was checked. Redirects are followed by
     * hand, so each address they lead to is checked and connected to the same way;
     * download links on GitHub and many CDNs redirect to the file.
     */
    private function readExternal(string $url): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $deadline = microtime(true) + self::READ_DEADLINE;
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $remaining = $deadline - microtime(true);
            $resolve = $this->publicAddress($url);
            if ($resolve === null || $remaining <= 0) {
                return null;
            }

            $result = $this->readExternalOnce($url, $resolve, $remaining);
            if (!is_array($result)) {
                return $result;
            }

            try {
                $url = (string) UriResolver::resolve(new Uri($url), new Uri($result['location']));
            } catch (InvalidArgumentException) {
                // A redirect to a location that is not a link gives nothing to read.
                return null;
            }
        }

        return null;
    }

    /**
     * Reads the start of one link at the checked address. The download is stopped
     * once enough of it has arrived. Returns the bytes read, null when the link
     * gave nothing usable, or the location of a redirect.
     *
     * @return string|array{location: string}|null
     */
    private function readExternalOnce(string $url, string $resolve, float $timeout): string|array|null
    {
        $sink = fopen('php://temp', 'w+b');
        if ($sink === false) {
            return null;
        }

        $successful = false;
        $response = null;

        try {
            $response = Http::setHandler(new CurlHandler())
                ->timeout((int) ceil($timeout))
                ->connectTimeout(5)
                ->withHeaders(['Range' => 'bytes=0-' . (self::MAX_READ_BYTES - 1)])
                ->withOptions([
                    'allow_redirects' => false,
                    'curl' => [CURLOPT_RESOLVE => [$resolve]],
                    'sink' => $sink,
                    'on_headers' => function (ResponseInterface $response) use (&$successful) {
                        $status = $response->getStatusCode();
                        $successful = $status >= 200 && $status < 300;
                        if (!$successful && ($status < 300 || $status >= 400)) {
                            throw new Exception('The archive could not be downloaded.');
                        }
                    },
                    'progress' => function ($total, int $downloaded) {
                        if ($downloaded >= self::MAX_READ_BYTES) {
                            throw new Exception('Enough of the archive was downloaded.');
                        }
                    },
                ])
                ->get($url);
        } catch (Exception) {
            // Stopping the download, or the deadline, ends the request with an error.
            // What arrived before that is still in the sink.
        }

        if ($response?->redirect() && $response->header('Location') !== '') {
            fclose($sink);

            return ['location' => $response->header('Location')];
        }

        if ($response !== null) {
            // The response body is the sink.
            $compressed = $response->successful() ? substr($response->body(), 0, self::MAX_READ_BYTES) : false;
        } else {
            rewind($sink);
            $compressed = $successful ? stream_get_contents($sink, self::MAX_READ_BYTES) : false;
        }
        fclose($sink);

        return is_string($compressed) && $compressed !== '' ? $compressed : null;
    }

    /**
     * The curl resolve entry (host:port:address) for a link whose host only has
     * public addresses, or null when it has none or any of them is not public.
     */
    private function publicAddress(string $url): ?string
    {
        if (!in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
            return null;
        }

        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return null;
        }

        $host = trim($host, '[]');
        $port = parse_url($url, PHP_URL_PORT) ?? (parse_url($url, PHP_URL_SCHEME) === 'https' ? 443 : 80);

        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $addresses = [$host];
        } else {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA) ?: [];
            $addresses = array_values(array_filter(array_map(fn (array $record) => $record['ip'] ?? $record['ipv6'] ?? null, $records)));
        }

        if ($addresses === []) {
            return null;
        }

        foreach ($addresses as $address) {
            // Global addresses only, which also leaves out shared (100.64.0.0/10) and
            // benchmarking (198.18.0.0/15) ranges that are neither private nor reserved.
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_GLOBAL_RANGE) === false) {
                return null;
            }
        }

        $address = str_contains($addresses[0], ':') ? "[{$addresses[0]}]" : $addresses[0];

        return "$host:$port:$address";
    }

    /**
     * Reads the configuration from the start of a gzipped tar archive.
     *
     * @return array<string, mixed>|null
     */
    public function fromArchiveStart(string $compressed): ?array
    {
        $inflate = @inflate_init(ZLIB_ENCODING_GZIP);
        if ($inflate === false) {
            return null;
        }

        // Inflate in small steps and stop once the header and the largest allowed
        // configuration are in, a small archive can expand to far more than that.
        $limit = 512 + self::MAX_CONFIG_BYTES;
        $tar = '';
        foreach (str_split($compressed, 256) as $chunk) {
            $output = @inflate_add($inflate, $chunk, ZLIB_SYNC_FLUSH);
            if ($output === false) {
                break;
            }

            $tar .= $output;
            if (strlen($tar) >= $limit) {
                $tar = substr($tar, 0, $limit);
                break;
            }
        }

        if (strlen($tar) < 512) {
            return null;
        }

        $header = substr($tar, 0, 512);
        $name = rtrim(substr($header, 0, 100), "\0");
        $prefix = rtrim(substr($header, 345, 155), "\0");
        if ($prefix !== '') {
            $name = "$prefix/$name";
        }
        $type = $header[156];

        if (!in_array(preg_replace('#^\./#', '', $name), [self::CONFIG_FILE], true) || !in_array($type, ['0', "\0"], true)) {
            return null;
        }

        $size = octdec(trim(substr($header, 124, 12), "\0 "));
        if ($size <= 0 || $size > self::MAX_CONFIG_BYTES || strlen($tar) < 512 + $size) {
            return null;
        }

        $config = json_decode(substr($tar, 512, (int) $size), true);

        return is_array($config) ? $config : null;
    }
}
