<?php

namespace App\Services\Backups;

use App\Exceptions\Service\Backup\TooManyBackupsException;
use App\Extensions\BackupAdapter\BackupAdapterService;
use App\Models\Backup;
use App\Models\BackupHost;
use App\Models\Server;
use Exception;
use Illuminate\Database\ConnectionInterface;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Throwable;
use Webmozart\Assert\Assert;

class InitiateBackupService
{
    /** @var string[] */
    private array $ignoredFiles;

    private bool $isLocked = false;

    private bool $isScheduled = false;

    /**
     * InitiateBackupService constructor.
     */
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly DeleteBackupService $deleteBackupService,
        private readonly BackupAdapterService $backupService
    ) {}

    /**
     * Set if the backup should be locked once it is created which will prevent
     * its deletion by users or automated system processes.
     */
    public function setIsLocked(bool $isLocked): self
    {
        $this->isLocked = $isLocked;

        return $this;
    }

    /**
     * Set if the backup was initiated by a schedule rather than a user.
     */
    public function setIsScheduled(bool $isScheduled): self
    {
        $this->isScheduled = $isScheduled;

        return $this;
    }

    /**
     * Sets the files to be ignored by this backup.
     *
     * @param  string[]|null  $ignored
     */
    public function setIgnoredFiles(?array $ignored): self
    {
        if (is_array($ignored)) {
            foreach ($ignored as $value) {
                Assert::string($value);
            }
        }

        // Set the ignored files to be any values that are not empty in the array. Don't use
        // the PHP empty function here incase anything that is "empty" by default (0, false, etc.)
        // were passed as a file or folder name.
        $this->ignoredFiles = is_null($ignored) ? [] : array_filter($ignored, function ($value) {
            return strlen($value) > 0;
        });

        return $this;
    }

    /**
     * Initiates the backup process for a server on daemon.
     *
     * @throws Throwable
     * @throws TooManyBackupsException
     * @throws TooManyRequestsHttpException
     */
    public function handle(Server $server, ?string $name = null, bool $override = false): Backup
    {
        $limit = config('backups.throttles.limit');
        $period = config('backups.throttles.period');
        if ($period > 0) {
            $previous = $server
                ->backups()
                ->where('created_at', '>=', now()->subSeconds($period))
                ->nonFailed()
                ->get();

            if ($previous->count() >= $limit) {
                $message = sprintf('Only %d backups may be generated within a %d second span of time.', $limit, $period);

                throw new TooManyRequestsHttpException((int) now()->diffInSeconds($previous->last()->created_at->addSeconds((int) $period)), $message);
            }
        }

        // Check if the server has reached or exceeded its backup limit.
        // completed_at == null will cover any ongoing backups, while is_successful == true will cover any completed backups.
        $successful = $server->backups()->nonFailed();
        if (!$server->backup_limit || $successful->count() >= $server->backup_limit) {
            // Do not allow the user to continue if this server is already at its limit and can't override.
            throw_if(!$override || $server->backup_limit <= 0, new TooManyBackupsException($server->backup_limit));

            // Get the oldest backup the server has that is not "locked" (indicating a backup that should
            // never be automatically purged). If we find a backup we will delete it and then continue with
            // this process. If no backup is found that can be used an exception is thrown.
            $oldest = $successful->where('is_locked', false)->orderBy('created_at')->first();
            throw_unless($oldest, new TooManyBackupsException($server->backup_limit));

            $this->deleteBackupService->handle($oldest);
        }

        // TODO: select backup host via frontend
        $backupHost = $server->node->backupHosts()->first();
        if (!$backupHost) {
            $backupHost = BackupHost::doesntHave('nodes')->firstOrFail();
        }

        $schema = $this->backupService->get($backupHost->schema);
        if (!$schema) {
            throw new Exception('Backup host has unknown backup adapter.');
        }

        return $this->connection->transaction(function () use ($backupHost, $schema, $server, $name) {
            $backup = Backup::create([
                'server_id' => $server->id,
                'uuid' => Uuid::uuid4()->toString(),
                'name' => trim($name) ?: sprintf('Backup at %s', now()->toDateTimeString()),
                'ignored_files' => array_values($this->ignoredFiles ?? []),
                'backup_host_id' => $backupHost->id,
                'is_locked' => $this->isLocked,
                'is_scheduled' => $this->isScheduled,
            ]);

            $schema->createBackup($backup);

            return $backup;
        });
    }
}
