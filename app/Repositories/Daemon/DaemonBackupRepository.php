<?php

namespace App\Repositories\Daemon;

use App\Models\Backup;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;

class DaemonBackupRepository extends DaemonRepository
{
    /**
     * Tells the remote Daemon to begin generating a backup for the server.
     *
     * @throws ConnectionException
     */
    public function create(Backup $backup): Response
    {
        return $this->getHttpClient()->post("/api/servers/{$this->server->uuid}/backup",
            [
                'adapter' => $backup->backupHost->schema,
                'uuid' => $backup->uuid,
                'ignore' => implode("\n", $backup->ignored_files),
                'pelican_config' => $backup->pelican_config,
            ]
        );
    }

    /**
     * Sends a request to daemon to begin restoring a backup for a server.
     *
     * @throws ConnectionException
     */
    public function restore(Backup $backup, ?string $url = null, bool $truncate = false): Response
    {
        return $this->getHttpClient()->post("/api/servers/{$this->server->uuid}/backup/$backup->uuid/restore",
            [
                'adapter' => $backup->backupHost->schema,
                'truncate_directory' => $truncate,
                'download_url' => $url ?? '',
            ]
        );
    }

    /**
     * Sends a request to daemon to restore a backup into a new server created
     * from it. The backup may belong to another server, and may be stored on
     * another node or in S3, in which case it is downloaded from the given url.
     *
     * @throws ConnectionException
     */
    public function restoreInto(string $backupUuid, string $adapter, ?string $sourceServerUuid, ?string $url): Response
    {
        return $this->getHttpClient()->post("/api/servers/{$this->server->uuid}/backup/$backupUuid/restore",
            array_filter([
                'adapter' => $adapter,
                'truncate_directory' => true,
                'source_server' => $sourceServerUuid,
                'download_url' => $url,
            ], fn ($value) => $value !== null)
        );
    }

    /**
     * Deletes a backup that was kept after its server was deleted from the node.
     *
     * @throws ConnectionException
     */
    public function deleteKept(Backup $backup): Response
    {
        return $this->getHttpClient()->delete("/api/backups/$backup->server_uuid/$backup->uuid");
    }

    /**
     * Deletes a backup from the daemon.
     *
     * @throws ConnectionException
     */
    public function delete(Backup $backup): Response
    {
        return $this->getHttpClient()->delete("/api/servers/{$this->server->uuid}/backup/$backup->uuid");
    }
}
