<?php

namespace App\Console\Commands\Maintenance;

use App\Services\Maintenance\UpdateSnapshotService;
use Illuminate\Console\Command;
use Spatie\Health\Commands\RunHealthChecksCommand;

/**
 * Runs the existing Panel health checks after an update and points to rollback guidance.
 */
class FinishUpdateCommand extends Command
{
    protected $description = 'Validate the Panel after an update and show rollback guidance when validation fails.';

    protected $signature = 'p:maintenance:finish-update
        {--snapshot= : Pre-update snapshot directory. The newest snapshot is used by default.}';

    /**
     * Keep the application in maintenance mode when any registered health check fails.
     */
    public function handle(UpdateSnapshotService $snapshots): int
    {
        $result = $this->call(RunHealthChecksCommand::class, [
            '--no-notification' => true,
            '--fail-command-on-failing-check' => true,
        ]);

        if ($result === self::SUCCESS) {
            $this->info(trans('commands.update.healthy'));

            return self::SUCCESS;
        }

        $snapshotOption = $this->option('snapshot');
        $snapshot = is_string($snapshotOption) && $snapshotOption !== ''
            ? $snapshots->fromPath($snapshotOption)
            : $snapshots->latest();

        if ($snapshot === null) {
            $this->error(trans('commands.update.snapshot_missing'));

            return self::FAILURE;
        }

        $this->error(trans('commands.update.unhealthy', ['path' => $snapshot->rollbackGuide]));

        return self::FAILURE;
    }
}
