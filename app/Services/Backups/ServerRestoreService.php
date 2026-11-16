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
use Closure;
use Exception;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
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

    /** How long the lock for restoring a backup is held at most, besides the daemon request, in seconds. */
    private const LOCK_SECONDS = 60;

    /** How long a restore waits for that lock, in seconds. */
    private const LOCK_WAIT_SECONDS = 15;

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
            // Choosing the uuid the node reports back with checks what else is restoring the
            // backup, and recording it is what those checks look for.
            [$identifier, $adapter, $sourceServer, $url] = $this->whileRestoring($restore->backup, function () use ($restore) {
                $payload = $this->payload($restore);
                $restore->update(['callback_uuid' => $payload[0]]);

                return $payload;
            });

            // A suspended server stays suspended while its files are restored. The update
            // checks the stored status, so a suspension made meanwhile is not overwritten.
            Server::query()
                ->whereKey($server->id)
                ->where(fn ($query) => $query->whereNull('status')->orWhere('status', '!=', ServerState::Suspended))
                ->update(['status' => ServerState::RestoringBackup]);
            $server->refresh();

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

        // The install notification is sent before the files are restored, so the
        // owner is told separately when the server is ready.
        $server->loadMissing('user');
        Notification::make()
            ->success()
            ->title(trans('admin/backup.restore.completed_title', ['server' => $server->name]))
            ->body(trans('admin/backup.restore.completed_body', ['source' => $this->sourceDescription($restore)]))
            ->sendToDatabase($server->user);

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
        Server::query()
            ->whereKey($server->id)
            ->where('status', ServerState::RestoringBackup)
            ->update(['status' => null]);

        $server->refresh();
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
     * Runs $callback holding the lock for restoring the given backup. Restores of a
     * backup into a new server and into its own server both check what the other is
     * doing and then start, which has to happen under one lock.
     *
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     *
     * @throws LockTimeoutException
     */
    public function whileRestoring(?Backup $backup, Closure $callback): mixed
    {
        if (!$backup) {
            return $callback();
        }

        return Cache::lock("backup-restore:$backup->uuid", $this->lockSeconds())->block(self::LOCK_WAIT_SECONDS, $callback);
    }

    /**
     * An in-place restore holds the lock while it waits for the daemon, so the lock
     * has to outlast the longest daemon request the timeouts allow.
     */
    public function lockSeconds(): int
    {
        return self::LOCK_SECONDS + (int) config('panel.guzzle.timeout') + (int) config('panel.guzzle.connect_timeout');
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
