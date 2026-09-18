<?php

namespace App\Console\Commands\Maintenance;

use App\Services\Maintenance\UpdateCompatibilityService;
use App\Services\Maintenance\UpdateSnapshotService;
use Illuminate\Console\Command;
use Throwable;

/**
 * Prepares a guarded update by validating the target release and capturing current state.
 */
class PrepareUpdateCommand extends Command
{
    protected $description = 'Capture the current Panel state and verify the target release before an update.';

    protected $signature = 'p:maintenance:prepare-update
        {--source= : Extracted target release directory containing composer.json and composer.lock.}
        {--target-version= : Target Panel version recorded in the snapshot metadata.}
        {--composer= : Absolute Composer executable or composer.phar path.}
        {--retain= : Number of protected update snapshots to retain.}';

    /**
     * Validate compatibility before creating any rollback artifacts.
     */
    public function handle(
        UpdateCompatibilityService $compatibility,
        UpdateSnapshotService $snapshots,
    ): int {
        $source = $this->option('source');
        if (!is_string($source) || $source === '') {
            $this->error(trans('commands.update.source_required'));

            return self::FAILURE;
        }

        $composer = $this->option('composer');
        $retained = $this->option('retain');
        $targetVersion = $this->option('target-version');
        if ($retained !== null && (!is_numeric($retained) || (int) $retained < 1)) {
            $this->error(trans('commands.update.retention_invalid'));

            return self::FAILURE;
        }

        try {
            $compatibility->assertCompatible(
                $source,
                is_string($composer) && $composer !== '' ? $composer : null,
            );
            $this->info(trans('commands.update.compatibility_passed'));

            $snapshot = $snapshots->capture(
                targetVersion: is_string($targetVersion) ? $targetVersion : null,
                retainedSnapshots: $retained !== null ? (int) $retained : null,
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            $this->error(trans('commands.update.preparation_failed'));

            return self::FAILURE;
        }

        $this->info(trans('commands.update.snapshot_created', ['path' => $snapshot->path]));
        $this->line(trans('commands.update.database_guidance', ['guidance' => $snapshot->databaseGuidance]));
        $this->info(trans('commands.update.ready'));

        return self::SUCCESS;
    }
}
