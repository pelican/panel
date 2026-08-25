<?php

namespace App\Console\Commands\Environment;

use App\Services\Environment\InstallationHealthService;
use App\Traits\Commands\DisplaysHealthResults;
use Illuminate\Console\Command;

class EnvironmentHealthCommand extends Command
{
    use DisplaysHealthResults;

    protected $description = 'Validate the complete Panel installation after an install or update.';

    protected $signature = 'p:environment:health';

    /**
     * Run and report the complete post-installation health checks.
     */
    public function handle(InstallationHealthService $health): int
    {
        $results = $health->completeInstallation();

        $this->displayHealthResults($results);

        if ($health->hasFailures($results)) {
            $this->error(trans('commands.environment_check.health_failed'));

            return self::FAILURE;
        }

        $this->info(trans('commands.environment_check.health_passed'));

        return self::SUCCESS;
    }
}
