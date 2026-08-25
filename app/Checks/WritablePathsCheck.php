<?php

namespace App\Checks;

use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

class WritablePathsCheck extends Check
{
    /** @var string[]|null */
    protected ?array $paths = null;

    /**
     * Override the filesystem paths that must be writable.
     *
     * @param  string[]  $paths
     */
    public function paths(array $paths): self
    {
        $this->paths = $paths;

        return $this;
    }

    /**
     * Verify that every required installer path is writable.
     */
    public function run(): Result
    {
        $paths = $this->paths ?? [
            storage_path(),
            base_path('bootstrap/cache'),
            file_exists(base_path('.env')) ? base_path('.env') : base_path(),
        ];

        $notWritable = array_values(array_filter(
            $paths,
            fn (string $path) => !is_writable($path),
        ));

        $result = Result::make()->meta([
            'paths' => implode(', ', $paths),
            'not_writable' => implode(', ', $notWritable),
            'remediation' => trans('installer.health.paths.remediation'),
        ]);

        return $notWritable === []
            ? $result->ok(trans('installer.health.paths.passed'))
            : $result->failed(trans('installer.health.paths.failed', [
                'paths' => implode(', ', $notWritable),
            ]));
    }
}
