<?php

namespace App\Checks;

use App\Enums\DatabaseDriver;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class PhpExtensionsCheck extends Check
{
    public const REQUIRED_EXTENSIONS = [
        'bcmath',
        'curl',
        'gd',
        'intl',
        'json',
        'mbstring',
        'pdo',
        'xml',
        'zip',
    ];

    /** @var string[] */
    protected array $requiredExtensions = self::REQUIRED_EXTENSIONS;

    /**
     * Override the PHP extensions required by this check.
     *
     * @param  string[]  $extensions
     */
    public function requireExtensions(array $extensions): self
    {
        $this->requiredExtensions = $extensions;

        return $this;
    }

    /**
     * Verify the required PHP and PDO extensions are loaded.
     */
    public function run(): Result
    {
        $missing = array_values(array_filter(
            $this->requiredExtensions,
            fn (string $extension) => !extension_loaded($extension),
        ));

        $databaseExtensions = array_unique(array_map(
            fn (DatabaseDriver $driver) => $driver->requiredExtension(),
            DatabaseDriver::cases(),
        ));

        $hasDatabaseExtension = array_filter($databaseExtensions, extension_loaded(...)) !== [];

        if (!$hasDatabaseExtension) {
            $missing[] = implode(', ', $databaseExtensions);
        }

        $result = Result::make()->meta([
            'missing' => implode(', ', $missing),
            'remediation' => trans('installer.health.extensions.remediation'),
        ]);

        return $missing === []
            ? $result->ok(trans('installer.health.extensions.passed'))
            : $result->failed(trans('installer.health.extensions.failed', [
                'extensions' => implode(', ', $missing),
            ]));
    }
}
