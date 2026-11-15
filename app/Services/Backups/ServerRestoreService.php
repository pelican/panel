<?php

namespace App\Services\Backups;

use App\Enums\ServerState;
use App\Extensions\BackupAdapter\BackupAdapterService;
use App\Extensions\BackupAdapter\Schemas\S3BackupSchema;
use App\Extensions\BackupAdapter\Schemas\WingsBackupSchema;
use App\Facades\Activity;
use App\Models\Backup;
use App\Models\Node;
use App\Models\Server;
use App\Models\ServerRestore;
use App\Repositories\Daemon\DaemonBackupRepository;
use App\Repositories\Daemon\DaemonServerRepository;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Http\Client\RequestException;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * Restores a backup into a server that was created from it. The restore is
 * recorded when the server is created and started once its (skipped) install
 * has finished.
 */
class ServerRestoreService
{
    /** How long download links for a restore stay valid, in minutes. */
    public const LINK_LIFETIME = 60;

    public function __construct(
        private readonly BackupAdapterService $backupAdapterService,
        private readonly DaemonBackupRepository $daemonBackupRepository,
        private readonly DaemonServerRepository $daemonServerRepository,
    ) {}

    /**
     * Records that the given server should have the source restored into it
     * once it has been installed.
     */
    public function create(Server $server, RestoreSource $source, bool $startOnCompletion): ServerRestore
    {
        $backup = $source->getBackup();

        return ServerRestore::query()->create([
            'uuid' => Uuid::uuid4()->toString(),
            'server_id' => $server->id,
            'backup_id' => $backup?->id,
            'backup_host_id' => $backup->backup_host_id ?? $source->backupHostId,
            'source_key' => $source->key,
            'source_url' => $source->url,
            'start_on_completion' => $startOnCompletion,
        ]);
    }

    /**
     * Asks the server's node to restore the backup.
     */
    public function start(ServerRestore $restore): void
    {
        // Claim the restore, the install can be reported more than once.
        $claimed = ServerRestore::query()
            ->whereKey($restore->id)
            ->where('status', ServerRestore::STATUS_PENDING)
            ->update(['status' => ServerRestore::STATUS_RUNNING, 'error' => null]);
        if ($claimed === 0) {
            return;
        }

        $restore->refresh();
        $server = $restore->server;

        try {
            [$identifier, $adapter, $sourceServer, $url] = $this->payload($restore);

            $restore->update(['callback_uuid' => $identifier]);
            // A suspended server stays suspended while its files are restored.
            if ($server->status !== ServerState::Suspended) {
                $server->update(['status' => ServerState::RestoringBackup]);
            }

            $this->daemonBackupRepository->setServer($server)->restoreInto($identifier, $adapter, $sourceServer, $url);

            Activity::event('server:backup.restore-as-new')
                ->subject($server)
                ->property(['source' => $this->sourceDescription($restore)])
                ->log();
        } catch (Throwable $exception) {
            $message = $exception instanceof RequestException
                ? ($exception->response->json('error') ?? $exception->getMessage())
                : $exception->getMessage();

            $this->fail($restore, $message);
        }
    }

    /**
     * Finds the running restore the node is reporting on, by the uuid it was
     * started with. payload() keeps that uuid unique among running restores.
     */
    public function findRunning(string $uuid, Node $node): ?ServerRestore
    {
        return ServerRestore::query()
            ->where('status', ServerRestore::STATUS_RUNNING)
            ->where('callback_uuid', $uuid)
            ->whereHas('server', fn ($query) => $query->where('node_id', $node->id))
            ->first();
    }

    /**
     * Records the result reported by the node.
     */
    public function complete(ServerRestore $restore, bool $successful): void
    {
        if (!$successful) {
            $this->fail($restore, trans('admin/backup.restore.failed_on_node'));

            return;
        }

        $server = $restore->server;
        $restore->update(['status' => ServerRestore::STATUS_COMPLETED, 'error' => null]);
        $this->clearRestoringStatus($server);

        Activity::event('server:backup.restore-complete')
            ->subject($server)
            ->property('name', $this->sourceDescription($restore))
            ->log();

        if ($restore->start_on_completion && $server->status !== ServerState::Suspended) {
            try {
                $this->daemonServerRepository->setServer($server)->power('start');
            } catch (Exception $exception) {
                report($exception);
            }
        }
    }

    public function fail(ServerRestore $restore, string $error): void
    {
        $server = $restore->server;
        $restore->update(['status' => ServerRestore::STATUS_FAILED, 'error' => $error]);
        $this->clearRestoringStatus($server);

        Activity::event('server:backup.restore-failed')
            ->subject($server)
            ->property('name', $this->sourceDescription($restore))
            ->log();

        $server->loadMissing('user');
        Notification::make()
            ->danger()
            ->title(trans('admin/backup.restore.failed_title', ['server' => $server->name]))
            ->body($error)
            ->sendToDatabase($server->user);
    }

    /**
     * Only the restoring status is cleared, a server suspended in the meantime
     * stays suspended.
     */
    private function clearRestoringStatus(Server $server): void
    {
        $server->refresh();
        if ($server->status === ServerState::RestoringBackup) {
            $server->update(['status' => null]);
        }
    }

    /**
     * The backup identifier, adapter, source server and download link to send
     * to the node.
     *
     * @return array{string, string, string|null, string|null}
     *
     * @throws Exception
     */
    private function payload(ServerRestore $restore): array
    {
        $backup = $restore->backup;
        if ($backup) {
            $schema = $this->backupAdapterService->get($backup->backupHost->schema);

            if ($schema instanceof WingsBackupSchema) {
                // The node restores it locally when it has it, otherwise it is downloaded
                // from the node that holds it. Either way the node reports back with the
                // backup's uuid, so while that uuid is in use on the node the archive is
                // streamed like any other link and reported with the restore's uuid.
                if ($this->backupInUse($backup, $restore)) {
                    return [$restore->uuid, 's3', null, $schema->getRestoreLink($backup, self::LINK_LIFETIME)];
                }

                $url = $backup->sourceNode()?->is($restore->server->node) ? null : $schema->getRestoreLink($backup, self::LINK_LIFETIME);

                return [$backup->uuid, 'wings', $backup->sourceServerUuid(), $url];
            }

            if ($schema instanceof S3BackupSchema) {
                return [$restore->uuid, 's3', null, $schema->getObjectLink($backup->backupHost, $schema->objectKey($backup), self::LINK_LIFETIME)];
            }

            throw new Exception(trans('admin/backup.restore.unsupported_adapter'));
        }

        if ($restore->source_key) {
            $backupHost = $restore->backupHost;
            $schema = $backupHost ? $this->backupAdapterService->get($backupHost->schema) : null;
            if (!$schema instanceof S3BackupSchema) {
                throw new Exception(trans('admin/backup.restore.unsupported_adapter'));
            }

            return [$restore->uuid, 's3', null, $schema->getObjectLink($backupHost, $restore->source_key, self::LINK_LIFETIME)];
        }

        if ($restore->source_url) {
            // Wings streams any link with the s3 adapter.
            return [$restore->uuid, 's3', null, $restore->source_url];
        }

        throw new Exception(trans('admin/backup.restore.missing_backup'));
    }

    /**
     * Whether the node is restoring the backup into a new server under the backup's
     * own uuid. The node reports a restore of the backup into its own server with
     * the same uuid, so that restore has to wait.
     */
    public function isRestoringIntoNewServer(Backup $backup, int $nodeId, ?ServerRestore $except = null): bool
    {
        return ServerRestore::query()
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->where('status', ServerRestore::STATUS_RUNNING)
            ->where('callback_uuid', $backup->uuid)
            ->whereHas('server', fn ($query) => $query->where('node_id', $nodeId))
            ->exists();
    }

    /**
     * Whether the node is already restoring this backup under its own uuid, into
     * another new server or into the server it belongs to.
     */
    private function backupInUse(Backup $backup, ServerRestore $restore): bool
    {
        $node = $restore->server->node_id;

        return $this->isRestoringIntoNewServer($backup, $node, $restore)
            || ($backup->server?->node_id === $node && $backup->server->status === ServerState::RestoringBackup);
    }

    private function sourceDescription(ServerRestore $restore): string
    {
        return match (true) {
            $restore->backup !== null => $restore->backup->name,
            $restore->source_key !== null => $restore->source_key,
            default => (string) parse_url((string) $restore->source_url, PHP_URL_HOST),
        };
    }
}
