<?php

namespace App\Services\Maintenance;

use App\Data\UpdateSnapshotData;
use App\Services\Helpers\SoftwareVersionService;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Captures, validates, and prunes protected Panel update snapshots.
 */
class UpdateSnapshotService
{
    private const REQUIRED_ARTIFACTS = [
        '.env',
        'composer.json',
        'composer.lock',
        'DATABASE-BACKUP.txt',
        'ROLLBACK.md',
        'metadata.json',
    ];

    /**
     * Build the snapshot service with version discovery and filesystem access.
     */
    public function __construct(
        private readonly SoftwareVersionService $versionService,
        private readonly Filesystem $files,
    ) {}

    /**
     * Create a complete snapshot before any application files or migrations are changed.
     *
     * @throws RuntimeException when a required artifact cannot be captured or pruned
     */
    public function capture(
        ?string $targetVersion = null,
        ?string $snapshotRoot = null,
        ?string $environmentPath = null,
        ?int $retainedSnapshots = null,
    ): UpdateSnapshotData {
        $snapshotRoot ??= storage_path('app/private/update-snapshots');
        $environmentPath ??= base_path('.env');
        $retainedSnapshots ??= (int) config('panel.updates.retained_snapshots', 3);

        if (!$this->files->isFile($environmentPath)) {
            throw new RuntimeException(trans('commands.update.environment_missing'));
        }

        $this->files->ensureDirectoryExists($snapshotRoot, 0700, true);
        $snapshotPath = $snapshotRoot . DIRECTORY_SEPARATOR . now()->format('Ymd-His') . '-' . Str::lower(Str::random(6));
        $this->files->ensureDirectoryExists($snapshotPath, 0700, true);

        try {
            $environmentSnapshot = $snapshotPath . DIRECTORY_SEPARATOR . '.env';
            $this->copyRequiredArtifact($environmentPath, $environmentSnapshot);

            foreach (['composer.json', 'composer.lock'] as $file) {
                $this->copyRequiredArtifact(base_path($file), $snapshotPath . DIRECTORY_SEPARATOR . $file);
            }

            [$databaseGuidance, $databaseDriver] = $this->captureDatabaseState($snapshotPath);
            $rollbackGuide = $snapshotPath . DIRECTORY_SEPARATOR . 'ROLLBACK.md';
            $environmentMetadata = $this->environmentMetadata($environmentPath);

            $this->writeRequiredArtifact(
                $rollbackGuide,
                $this->rollbackGuide($snapshotPath, $environmentPath, $environmentMetadata, $databaseGuidance),
            );
            $this->writeRequiredArtifact($snapshotPath . DIRECTORY_SEPARATOR . 'metadata.json', json_encode([
                'created_at' => now()->toIso8601String(),
                'current_version' => $this->versionService->currentPanelVersion(),
                'target_version' => $targetVersion,
                'php_version' => PHP_VERSION,
                'database_driver' => $databaseDriver,
                'environment' => $environmentMetadata,
            ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

            $snapshot = $this->validatedSnapshot($snapshotPath);
            if ($snapshot === null) {
                throw new RuntimeException(trans('commands.update.snapshot_incomplete', ['path' => $snapshotPath]));
            }

            $this->pruneSnapshots($snapshotRoot, $snapshotPath, max(1, $retainedSnapshots));

            return $snapshot;
        } catch (Throwable $exception) {
            $this->files->deleteDirectory($snapshotPath);

            throw $exception;
        }
    }

    /**
     * Return the newest complete snapshot, ignoring incomplete or unrelated directories.
     */
    public function latest(?string $snapshotRoot = null): ?UpdateSnapshotData
    {
        $snapshotRoot ??= storage_path('app/private/update-snapshots');
        if (!$this->files->isDirectory($snapshotRoot)) {
            return null;
        }

        $directories = collect($this->files->directories($snapshotRoot))
            ->sortByDesc(fn (string $directory) => $this->files->lastModified($directory));

        foreach ($directories as $directory) {
            if (($snapshot = $this->validatedSnapshot($directory)) !== null) {
                return $snapshot;
            }
        }

        return null;
    }

    /**
     * Load a snapshot only when all required rollback artifacts are present and valid.
     */
    public function fromPath(string $path): ?UpdateSnapshotData
    {
        return $this->validatedSnapshot($path);
    }

    /**
     * Capture a consistent database state or write driver-specific backup guidance.
     *
     * @return array{string, string} guidance and resolved database driver
     */
    private function captureDatabaseState(string $snapshotPath): array
    {
        $connectionName = (string) config('database.default');

        try {
            $driver = DB::connection($connectionName)->getDriverName();
        } catch (Throwable $exception) {
            throw new RuntimeException(
                trans('commands.update.database_connection_failed', ['error' => $exception->getMessage()]),
                previous: $exception,
            );
        }

        if ($driver === 'sqlite') {
            $database = config("database.connections.{$connectionName}.database");
            if (!is_string($database) || $database === ':memory:' || !$this->files->isFile($database)) {
                throw new RuntimeException(trans('commands.update.sqlite_database_missing'));
            }

            $destination = $snapshotPath . DIRECTORY_SEPARATOR . 'database.sqlite';
            $this->backupSqliteDatabase($database, $destination);

            $guidance = trans('commands.update.database_backup_sqlite', ['path' => $destination]);
            $this->writeRequiredArtifact($snapshotPath . DIRECTORY_SEPARATOR . 'DATABASE-BACKUP.txt', $guidance);

            return [$guidance, $driver];
        }

        $guidance = match ($driver) {
            'mariadb', 'mysql' => trans('commands.update.database_backup_mysql'),
            'pgsql' => trans('commands.update.database_backup_pgsql'),
            default => trans('commands.update.database_backup_unknown', ['driver' => $driver]),
        };

        $this->writeRequiredArtifact($snapshotPath . DIRECTORY_SEPARATOR . 'DATABASE-BACKUP.txt', $guidance);

        return [$guidance, $driver];
    }

    /**
     * Use SQLite's VACUUM INTO support so committed WAL data is included consistently.
     */
    private function backupSqliteDatabase(string $database, string $destination): void
    {
        $connection = new PDO('sqlite:' . $database, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $connection->exec('PRAGMA busy_timeout = 5000');

        $quotedDestination = $connection->quote($destination);
        if (!is_string($quotedDestination)) {
            throw new RuntimeException(trans('commands.update.snapshot_write_failed', ['artifact' => $destination]));
        }

        $connection->exec("VACUUM INTO {$quotedDestination}");

        if (!$this->files->isFile($destination)) {
            throw new RuntimeException(trans('commands.update.snapshot_write_failed', ['artifact' => $destination]));
        }

        $this->protectArtifact($destination);
    }

    /**
     * Copy a required file and restrict the resulting snapshot artifact to its owner.
     */
    private function copyRequiredArtifact(string $source, string $destination): void
    {
        if (!$this->files->isFile($source) || !$this->files->copy($source, $destination)) {
            throw new RuntimeException(trans('commands.update.snapshot_write_failed', ['artifact' => $destination]));
        }

        $this->protectArtifact($destination);
    }

    /**
     * Write a required artifact and reject partial snapshot creation on failure.
     */
    private function writeRequiredArtifact(string $path, string $contents): void
    {
        if ($this->files->put($path, $contents) === false) {
            throw new RuntimeException(trans('commands.update.snapshot_write_failed', ['artifact' => $path]));
        }

        $this->protectArtifact($path);
    }

    /**
     * Apply restrictive permissions to files that may contain secrets or recovery data.
     */
    private function protectArtifact(string $path): void
    {
        if ($this->files->chmod($path, 0600) === false) {
            throw new RuntimeException(trans('commands.update.snapshot_permissions_failed', ['artifact' => $path]));
        }
    }

    /**
     * Validate every artifact before exposing a snapshot to update or rollback commands.
     */
    private function validatedSnapshot(string $path): ?UpdateSnapshotData
    {
        $path = rtrim($path, '/\\');
        if (!$this->files->isDirectory($path)) {
            return null;
        }

        foreach (self::REQUIRED_ARTIFACTS as $artifact) {
            if (!$this->files->isFile($path . DIRECTORY_SEPARATOR . $artifact)) {
                return null;
            }
        }

        try {
            $metadata = $this->files->json($path . DIRECTORY_SEPARATOR . 'metadata.json');
            if (!is_array($metadata) || !is_string($metadata['database_driver'] ?? null)) {
                return null;
            }

            if ($metadata['database_driver'] === 'sqlite'
                && !$this->files->isFile($path . DIRECTORY_SEPARATOR . 'database.sqlite')) {
                return null;
            }

            $databaseGuidance = trim($this->files->get($path . DIRECTORY_SEPARATOR . 'DATABASE-BACKUP.txt'));
            $rollbackGuide = trim($this->files->get($path . DIRECTORY_SEPARATOR . 'ROLLBACK.md'));
            if ($databaseGuidance === '' || $rollbackGuide === '') {
                return null;
            }
        } catch (Throwable) {
            return null;
        }

        return new UpdateSnapshotData(
            $path,
            $path . DIRECTORY_SEPARATOR . 'ROLLBACK.md',
            $databaseGuidance,
        );
    }

    /**
     * Keep the current snapshot plus the newest retained snapshots and securely limit growth.
     */
    private function pruneSnapshots(string $snapshotRoot, string $currentSnapshot, int $retain): void
    {
        $managedDirectories = collect($this->files->directories($snapshotRoot))
            ->filter(fn (string $directory) => preg_match('/^\d{8}-\d{6}-[a-z0-9]{6}$/', basename($directory)) === 1)
            ->sortByDesc(fn (string $directory) => $directory === $currentSnapshot
                ? PHP_INT_MAX
                : $this->files->lastModified($directory));

        foreach ($managedDirectories->slice($retain) as $directory) {
            if (!$this->files->deleteDirectory($directory)) {
                throw new RuntimeException(trans('commands.update.snapshot_prune_failed', ['path' => $directory]));
            }
        }
    }

    /**
     * Record the original `.env` owner, group, and permissions for exact restoration guidance.
     *
     * @return array{path: string, owner: int|null, group: int|null, mode: string}
     */
    private function environmentMetadata(string $environmentPath): array
    {
        $owner = @fileowner($environmentPath);
        $group = @filegroup($environmentPath);
        $permissions = @fileperms($environmentPath);

        return [
            'path' => $environmentPath,
            'owner' => is_int($owner) ? $owner : null,
            'group' => is_int($group) ? $group : null,
            'mode' => is_int($permissions) ? sprintf('%04o', $permissions & 0777) : '0600',
        ];
    }

    /**
     * Produce concrete, snapshot-specific rollback steps without changing the live installation.
     *
     * @param  array{path: string, owner: int|null, group: int|null, mode: string}  $environment
     */
    private function rollbackGuide(
        string $snapshotPath,
        string $environmentPath,
        array $environment,
        string $databaseGuidance,
    ): string {
        $source = $this->shellArgument($snapshotPath . DIRECTORY_SEPARATOR . '.env');
        $destination = $this->shellArgument($environmentPath);
        $ownership = is_int($environment['owner']) && is_int($environment['group'])
            ? "chown {$environment['owner']}:{$environment['group']} {$destination}"
            : '# Restore the original owner and group for the environment file.';

        return <<<MARKDOWN
# Pelican Panel update rollback

This snapshot was created before application files were changed. It contains secrets and must remain readable only by the Panel administrator.

1. Keep the Panel in maintenance mode: `php artisan down`.
2. Preserve logs and the failed release before replacing any files.
3. Restore the previous release files or Git revision.
4. Restore the environment file and its original protection:
   - `cp -- {$source} {$destination}`
   - `{$ownership}`
   - `chmod {$environment['mode']} {$destination}`
5. Restore the pre-update database only when the failed update ran migrations. Do not attempt to reverse migrations by hand.
6. Run `composer install --no-dev --optimize-autoloader` from the restored release.
7. Run `php artisan optimize:clear`.
8. Run `php artisan p:maintenance:finish-update --snapshot={$this->shellArgument($snapshotPath)}` and only run `php artisan up` after every health check passes.

Database backup guidance recorded before the update:

{$databaseGuidance}
MARKDOWN;
    }

    /**
     * Quote a filesystem path for the POSIX shell commands shown in rollback guidance.
     */
    private function shellArgument(string $value): string
    {
        return "'" . str_replace("'", "'\\''", $value) . "'";
    }
}
