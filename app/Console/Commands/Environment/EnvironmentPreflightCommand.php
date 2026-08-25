<?php

namespace App\Console\Commands\Environment;

use App\Checks\DatabaseCheck;
use App\Services\Environment\InstallationHealthService;
use App\Traits\Commands\DisplaysHealthResults;
use Illuminate\Console\Command;

class EnvironmentPreflightCommand extends Command
{
    use DisplaysHealthResults;

    protected $description = 'Verify server requirements before installing or reconfiguring the Panel.';

    protected $signature = 'p:environment:preflight
        {--with-database : Also verify the currently configured database extension and connection.}';

    /**
     * Run and report the pre-installation requirement checks.
     */
    public function handle(InstallationHealthService $health): int
    {
        $results = $health->systemRequirements();

        if ($this->option('with-database')) {
            $driverResult = $health->databaseDriverExtension((string) config('database.default'));
            $results->push($driverResult);

            if (!$health->hasFailures([$driverResult])) {
                $results->push($health->runCheck(
                    DatabaseCheck::new()->label(trans('installer.health.database.label')),
                ));
            }
        }

        $this->displayHealthResults($results);

        if ($health->hasFailures($results)) {
            $this->error(trans('commands.environment_check.preflight_failed'));

            return self::FAILURE;
        }

        $this->info(trans('commands.environment_check.preflight_passed'));

        return self::SUCCESS;
    }
}
