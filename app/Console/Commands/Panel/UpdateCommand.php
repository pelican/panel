<?php

namespace App\Console\Commands\Panel;

use App\Services\Helpers\PanelUpdateService;
use App\Services\Helpers\SoftwareVersionService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

class UpdateCommand extends Command
{
    protected $signature = 'p:update
        {version? : The version to update to, defaults to the latest release.}
        {--check : Only run the pre-update checks.}
        {--force-plugins : Update even if enabled plugins would become incompatible.}
        {--skip-db-backup : Update without a database backup when no dump tool is available.}
        {--from-request : Prepare the queued update for the update script of the new release.}
        {--running-jobs : Print how many queue jobs are running, used by the update script.}';

    protected $description = 'Updates the panel to a newer release, rolling back if anything fails.';

    public function handle(PanelUpdateService $updateService, SoftwareVersionService $versionService): int
    {
        if ($this->option('running-jobs')) {
            return $this->runningJobs($updateService);
        }

        if ($this->option('from-request')) {
            $request = $updateService->pullRequest();
            if ($request === null) {
                $this->error('There is no pending update request.');

                return self::FAILURE;
            }

            return $updateService->prepare($request, fn (string $output) => $this->output->write($output)) ? self::SUCCESS : self::FAILURE;
        }

        $target = $this->argument('version') ?? $versionService->latestPanelVersion();
        $target = $target === null ? null : ltrim($target, 'v');

        $preflight = $updateService->preflight($target);

        foreach ($preflight['errors'] as $error) {
            $this->error($error);
        }
        foreach ($preflight['warnings'] as $warning) {
            $this->warn($warning);
        }
        if (count($preflight['plugins']) > 0) {
            $this->warn(trans('admin/update.preflight.plugins', ['plugins' => implode(', ', $preflight['plugins'])]));
        }
        if ($preflight['needs_backup_confirmation']) {
            $this->warn(trans('admin/update.preflight.no_dump_tool'));
        }

        if (count($preflight['errors']) > 0) {
            return self::FAILURE;
        }

        if ($this->option('check')) {
            $this->info("Ready to update to $target.");

            return self::SUCCESS;
        }

        $forcePlugins = (bool) $this->option('force-plugins');
        if (count($preflight['plugins']) > 0 && !$forcePlugins) {
            if (!$this->input->isInteractive() || !$this->confirm('Update anyway? These plugins will be marked incompatible.')) {
                return self::FAILURE;
            }
            $forcePlugins = true;
        }

        $skipBackup = (bool) $this->option('skip-db-backup');
        if ($preflight['needs_backup_confirmation'] && !$skipBackup) {
            if (!$this->input->isInteractive() || !$this->confirm('Do you have your own database backup and want to continue without one?')) {
                return self::FAILURE;
            }
            $skipBackup = true;
        }

        if ($this->input->isInteractive() && !$this->confirm("Update the panel to $target now?", true)) {
            return self::FAILURE;
        }

        $secret = Str::random(40);
        $updateService->shareStateWithWebUser();

        try {
            // The confirmations above take time, a dashboard launch may have queued an update since the preflight.
            $updateService->whileLaunching(function () use ($updateService, $target, $secret, $forcePlugins, $skipBackup) {
                throw_if($updateService->isBusy(), new Exception(trans('admin/update.preflight.running')));
                $updateService->queue((string) $target, $secret, $forcePlugins, $skipBackup, null);
            });
        } catch (Exception $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('While the panel is in maintenance mode you can still reach it at ' . url($secret));

        // Replace this process with the update, so nothing of this release keeps running once the new one is swapped in.
        if (function_exists('pcntl_exec')) {
            $command = $updateService->updateCommand();
            @pcntl_exec($command[0], array_slice($command, 1), [...getenv(), ...$updateService->updateEnv()]);
        }

        $updateService->start();
        $this->info('The update is running in the background, follow it with: tail -f ' . storage_path('logs/panel-update.log'));

        return self::SUCCESS;
    }

    private function runningJobs(PanelUpdateService $updateService): int
    {
        try {
            $this->line((string) $updateService->runningQueueJobs());

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
