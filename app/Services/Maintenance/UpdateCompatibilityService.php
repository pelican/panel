<?php

namespace App\Services\Maintenance;

use Illuminate\Support\Facades\Process;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Throwable;

/**
 * Verifies the target release's locked Composer platform requirements before files change.
 */
class UpdateCompatibilityService
{
    /**
     * @throws RuntimeException when Composer cannot run or the target is incompatible
     */
    public function assertCompatible(string $source, ?string $composerBinary = null): void
    {
        $composerJson = $source . DIRECTORY_SEPARATOR . 'composer.json';
        $composerLock = $source . DIRECTORY_SEPARATOR . 'composer.lock';

        if (!is_file($composerJson) || !is_file($composerLock)) {
            throw new RuntimeException(trans('commands.update.compatibility_files_missing'));
        }

        $command = [
            ...$this->resolveComposerCommand($composerBinary),
            'check-platform-reqs',
            '--lock',
            '--no-dev',
            '--no-interaction',
        ];

        try {
            $result = Process::path($source)->timeout(120)->run($command);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                trans('commands.update.compatibility_exception', ['error' => $exception->getMessage()]),
                previous: $exception,
            );
        }

        if ($result->failed()) {
            $details = trim($result->errorOutput() . "\n" . $result->output());

            throw new RuntimeException(trans('commands.update.compatibility_command_failed', [
                'details' => $details !== '' ? $details : trans('commands.update.no_command_output'),
            ]));
        }
    }

    /**
     * Resolve an explicit executable or PHAR first, then a local composer.phar, and finally PATH.
     *
     * @return list<string>
     */
    private function resolveComposerCommand(?string $composerBinary): array
    {
        $finder = new ExecutableFinder();

        if (is_string($composerBinary) && trim($composerBinary) !== '') {
            $candidate = trim($composerBinary);
            $resolved = is_file($candidate) ? $candidate : $finder->find($candidate);

            if (!is_string($resolved)) {
                throw new RuntimeException(trans('commands.update.composer_binary_missing', [
                    'binary' => $candidate,
                ]));
            }

            return str_ends_with(strtolower($resolved), '.phar')
                ? [PHP_BINARY, $resolved]
                : [$resolved];
        }

        $localPhar = base_path('composer.phar');
        if (is_file($localPhar)) {
            return [PHP_BINARY, $localPhar];
        }

        $resolved = $finder->find('composer');
        if (!is_string($resolved)) {
            throw new RuntimeException(trans('commands.update.composer_binary_required'));
        }

        return [$resolved];
    }
}
