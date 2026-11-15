<?php

namespace App\Services\Backups;

use App\Extensions\BackupAdapter\BackupAdapterService;
use App\Extensions\BackupAdapter\Schemas\S3BackupSchema;
use App\Extensions\BackupAdapter\Schemas\WingsBackupSchema;
use Exception;
use Illuminate\Support\Facades\Http;

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

    public function __construct(private readonly BackupAdapterService $backupAdapterService) {}

    /**
     * @return array<string, mixed>|null
     */
    public function resolve(RestoreSource $source): ?array
    {
        return match ($source->type) {
            RestoreSource::TYPE_BACKUP => $this->fromBackup($source),
            RestoreSource::TYPE_S3 => $this->fromS3($source),
            RestoreSource::TYPE_URL => $this->fromUrl((string) $source->url),
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
     * @return array<string, mixed>|null
     */
    public function fromUrl(string $url): ?array
    {
        try {
            $response = Http::timeout(10)->connectTimeout(5)
                ->withOptions(['stream' => true, 'allow_redirects' => ['max' => 3, 'protocols' => ['http', 'https']]])
                ->get($url);

            if (!$response->successful()) {
                return null;
            }

            $body = $response->toPsrResponse()->getBody();
            $compressed = '';
            while (!$body->eof() && strlen($compressed) < self::MAX_READ_BYTES) {
                $compressed .= $body->read(min(16384, self::MAX_READ_BYTES - strlen($compressed)));
            }
            $body->close();
        } catch (Exception) {
            return null;
        }

        return $this->fromArchiveStart($compressed);
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

        $tar = @inflate_add($inflate, $compressed, ZLIB_SYNC_FLUSH);
        if ($tar === false || strlen($tar) < 512) {
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
        if ($size <= 0 || $size > 64 * 1024 || strlen($tar) < 512 + $size) {
            return null;
        }

        $config = json_decode(substr($tar, 512, (int) $size), true);

        return is_array($config) ? $config : null;
    }
}
