<?php

namespace App\Checks;

use Illuminate\Database\Migrations\Migrator;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Throwable;

class MigrationsCheck extends Check
{
    /**
     * Create a migration health check using the configured migrator.
     */
    public function __construct(private readonly Migrator $migrator) {}

    /**
     * Verify that the migration repository exists and has no pending migrations.
     */
    public function run(): Result
    {
        $result = Result::make()->meta([
            'remediation' => trans('installer.health.migrations.remediation'),
        ]);

        try {
            if (!$this->migrator->repositoryExists()) {
                return $result->failed(trans('installer.health.migrations.repository_missing'));
            }

            $files = $this->migrator->getMigrationFiles(database_path('migrations'));
            $pending = array_diff(array_keys($files), $this->migrator->getRepository()->getRan());

            return $pending === []
                ? $result->ok(trans('installer.health.migrations.passed'))
                : $result->failed(trans('installer.health.migrations.failed', ['count' => count($pending)]));
        } catch (Throwable $exception) {
            return $result->failed(trans('installer.health.migrations.exception', [
                'error' => $exception->getMessage(),
            ]));
        }
    }
}
