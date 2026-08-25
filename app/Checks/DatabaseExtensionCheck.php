<?php

namespace App\Checks;

use App\Enums\DatabaseDriver;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class DatabaseExtensionCheck extends Check
{
    protected DatabaseDriver|string $driver = DatabaseDriver::SQLite;

    /**
     * Select the database driver whose PHP extension should be checked.
     */
    public function driver(DatabaseDriver|string $driver): self
    {
        $this->driver = $driver;

        return $this;
    }

    /**
     * Verify that the configured database driver is supported and available.
     */
    public function run(): Result
    {
        $driver = is_string($this->driver) ? DatabaseDriver::tryFrom($this->driver) : $this->driver;

        if ($driver === null) {
            return Result::make()
                ->meta([
                    'driver' => $this->driver,
                    'remediation' => trans('installer.health.database_extension.unsupported_remediation'),
                ])
                ->failed(trans('installer.health.database_extension.unsupported', ['driver' => $this->driver]));
        }

        $extension = $driver->requiredExtension();
        $passed = extension_loaded($extension);

        $result = Result::make()->meta([
            'driver' => $driver->value,
            'extension' => $extension,
            'remediation' => trans('installer.health.extensions.remediation'),
        ]);

        return $passed
            ? $result->ok(trans('installer.health.database_extension.passed', ['extension' => $extension]))
            : $result->failed(trans('installer.health.database_extension.failed', [
                'driver' => $driver->getLabel(),
                'extension' => $extension,
            ]));
    }
}
