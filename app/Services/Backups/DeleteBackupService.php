<?php

namespace App\Services\Backups;

use App\Exceptions\Service\Backup\BackupLockedException;
use App\Extensions\BackupAdapter\BackupAdapterService;
use App\Models\Backup;
use Exception;
use Illuminate\Database\ConnectionInterface;
use Throwable;

class DeleteBackupService
{
    public function __construct(private readonly ConnectionInterface $connection, private readonly BackupAdapterService $backupService) {}

    /**
     * Deletes a backup from the system. If the backup is stored in S3 a request
     * will be made to delete that backup from the disk as well.
     *
     * @throws Throwable
     */
    public function handle(Backup $backup): void
    {
        // If the backup is marked as failed it can still be deleted, even if locked
        // since the UI doesn't allow you to unlock a failed backup in the first place.
        //
        // I also don't really see any reason you'd have a locked, failed backup to keep
        // around. The logic that updates the backup to the failed state will also remove
        // the lock, so this condition should really never happen.
        throw_if($backup->is_locked && ($backup->is_successful && !is_null($backup->completed_at)), new BackupLockedException());

        $schema = $this->backupService->get($backup->backupHost->schema);
        if (!$schema) {
            throw new Exception('Backup has unknown backup adapter.');
        }

        $this->connection->transaction(function () use ($schema, $backup) {
            try {
                $schema->deleteBackup($backup);
            } catch (Exception $exception) {
                throw_if($backup->is_successful, $exception);
            }

            $backup->delete();
        });
    }
}
