<?php

namespace App\Console\Commands\Panel;

use App\Services\Helpers\PanelUpdateService;
use App\Services\Helpers\SoftwareVersionService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class UpdateCommand extends Command
{
    protected $signature = 'p:update
        {version? : The version to update to, defaults to the latest release.}
        {--check : Only run the pre-update checks.}
        {--force-plugins : Update even if enabled plugins would become incompatible.}
        {--skip-db-backup : Update without a database backup when no dump tool is available.}
        {--from-request : Run the update that was started from the admin dashboard.}';

    protected $description = 'Updates the panel to a newer release, rolling back if anything fails.';

    public function handle(PanelUpdateService $updateService, SoftwareVersionService $versionService): int
    {
        if ($this->option('from-request')) {
            $request = $updateService->pullRequest();
            if ($request === null) {
                $this->error('There is no pending update request.');

                return self::FAILURE;
            }

            return $this->update($updateService, $request['target'], $request['secret'], (bool) $request['force_plugins'], (bool) $request['skip_db_backup'], $request['user_id']);
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
        $this->info('While the panel is in maintenance mode you can still reach it at ' . url($secret));

        return $this->update($updateService, (string) $target, $secret, $forcePlugins, $skipBackup, null);
    }

    private function update(PanelUpdateService $updateService, string $target, string $secret, bool $forcePlugins, bool $skipBackup, ?int $userId): int
    {
        $succeeded = $updateService->run($target, $secret, $forcePlugins, $skipBackup, $userId, fn (string $output) => $this->output->write($output));

        return $succeeded ? self::SUCCESS : self::FAILURE;
    }
}
