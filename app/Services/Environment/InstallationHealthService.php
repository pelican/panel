<?php

namespace App\Services\Environment;

use App\Checks\AdminUserCheck;
use App\Checks\ApplicationKeyCheck;
use App\Checks\CacheCheck;
use App\Checks\DatabaseCheck;
use App\Checks\DatabaseExtensionCheck;
use App\Checks\InstallationFlagCheck;
use App\Checks\MigrationsCheck;
use App\Checks\PhpExtensionsCheck;
use App\Checks\PhpVersionCheck;
use App\Checks\WritablePathsCheck;
use App\Enums\DatabaseDriver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;
use Spatie\Health\Enums\Status;
use Throwable;

class InstallationHealthService
{
    /**
     * Run the requirements that must pass before Panel configuration begins.
     *
     * @return Collection<int, Result>
     */
    public function systemRequirements(): Collection
    {
        return $this->run([
            PhpVersionCheck::new()->label(trans('installer.health.php.label')),
            PhpExtensionsCheck::new()->label(trans('installer.health.extensions.label')),
            WritablePathsCheck::new()->label(trans('installer.health.paths.label')),
        ]);
    }

    /**
     * Verify that a database driver is supported by the current PHP runtime.
     */
    public function databaseDriverExtension(DatabaseDriver|string $driver): Result
    {
        return $this->runCheck(
            DatabaseExtensionCheck::new()
                ->driver($driver)
                ->label(trans('installer.health.database_extension.label')),
        );
    }

    /**
     * Verify database connectivity using temporary, non-persistent settings.
     *
     * @param  array{host?: mixed, port?: mixed, database?: mixed, username?: mixed, password?: mixed}  $settings
     */
    public function databaseConnection(DatabaseDriver $driver, array $settings): Result
    {
        $database = (string) ($settings['database'] ?? '');
        if ($driver === DatabaseDriver::SQLite && !str_starts_with($database, '/') && $database !== ':memory:') {
            $database = database_path($database);
        }

        $configuration = $driver === DatabaseDriver::SQLite
            ? [
                'driver' => $driver->value,
                'database' => $database,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ]
            : [
                'driver' => $driver->value,
                'host' => $settings['host'] ?? null,
                'port' => $settings['port'] ?? $driver->defaultPort(),
                'database' => $database,
                'username' => $settings['username'] ?? null,
                'password' => $settings['password'] ?? null,
                'collation' => 'utf8mb4_unicode_ci',
                'strict' => true,
            ];

        $connection = '_panel_install_test';
        config()->set("database.connections.{$connection}", $configuration);
        DB::purge($connection);

        try {
            return $this->runCheck(
                DatabaseCheck::new()
                    ->connectionName($connection)
                    ->label(trans('installer.health.database.label')),
            );
        } finally {
            DB::disconnect($connection);
            DB::purge($connection);
        }
    }

    /**
     * Run health checks that require an already configured environment.
     *
     * @return Collection<int, Result>
     */
    public function configuredEnvironment(): Collection
    {
        return $this->systemRequirements()->concat($this->run([
            ApplicationKeyCheck::new()->label(trans('installer.health.app_key.label')),
            DatabaseCheck::new()->label(trans('installer.health.database.label')),
            MigrationsCheck::new()->label(trans('installer.health.migrations.label')),
            CacheCheck::new()->label(trans('installer.health.cache.label')),
        ]));
    }

    /**
     * Run the complete set of post-installation health checks.
     *
     * @return Collection<int, Result>
     */
    public function completeInstallation(): Collection
    {
        return $this->configuredEnvironment()->concat($this->run([
            AdminUserCheck::new()->label(trans('installer.health.admin.label')),
            InstallationFlagCheck::new()->label(trans('installer.health.installed.label')),
        ]));
    }

    /**
     * Execute a set of health checks and collect their normalized results.
     *
     * @param  iterable<Check>  $checks
     * @return Collection<int, Result>
     */
    public function run(iterable $checks): Collection
    {
        return collect($checks)->map(fn (Check $check) => $this->runCheck($check))->values();
    }

    /**
     * Execute one health check and convert thrown errors into crashed results.
     */
    public function runCheck(Check $check): Result
    {
        try {
            $result = $check->run();
        } catch (Throwable $exception) {
            report($exception);

            $result = $check->markAsCrashed()
                ->notificationMessage($exception->getMessage());
        }

        return $result
            ->check($check)
            ->endedAt(now());
    }

    /**
     * Determine whether any result represents a failed or crashed check.
     *
     * @param  iterable<Result>  $results
     */
    public function hasFailures(iterable $results): bool
    {
        foreach ($results as $result) {
            if (in_array($result->status, [Status::failed(), Status::crashed()], true)) {
                return true;
            }
        }

        return false;
    }
}
