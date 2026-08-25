<?php

namespace App\Checks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class PhpVersionCheck extends Check
{
    public const MINIMUM_VERSION = '8.3.0';

    protected string $minimumVersion = self::MINIMUM_VERSION;

    protected string $currentVersion = PHP_VERSION;

    /**
     * Override the minimum supported PHP version.
     */
    public function minimumVersion(string $version): self
    {
        $this->minimumVersion = $version;

        return $this;
    }

    /**
     * Override the current PHP version for evaluation.
     */
    public function currentVersion(string $version): self
    {
        $this->currentVersion = $version;

        return $this;
    }

    /**
     * Verify that the current PHP version meets the minimum requirement.
     */
    public function run(): Result
    {
        $passed = version_compare($this->currentVersion, $this->minimumVersion, '>=');

        $result = Result::make()
            ->meta([
                'current' => $this->currentVersion,
                'minimum' => $this->minimumVersion,
                'remediation' => trans('installer.health.php.remediation', ['minimum' => $this->minimumVersion]),
            ])
            ->shortSummary($this->currentVersion);

        return $passed
            ? $result->ok(trans('installer.health.php.passed', [
                'current' => $this->currentVersion,
                'minimum' => $this->minimumVersion,
            ]))
            : $result->failed(trans('installer.health.php.failed', [
                'current' => $this->currentVersion,
                'minimum' => $this->minimumVersion,
            ]));
    }
}
