<?php

namespace App\Traits\Commands;

use Spatie\Health\Checks\Result;
use Spatie\Health\Enums\Status;

trait DisplaysHealthResults
{
    /**
     * Render health check results as a console table with actionable failures.
     *
     * @param  iterable<Result>  $results
     */
    protected function displayHealthResults(iterable $results): void
    {
        $rows = [];

        foreach ($results as $result) {
            $remediation = $result->status === Status::ok()
                ? null
                : ($result->meta['remediation'] ?? null);

            $rows[] = [
                $result->check->getLabel(),
                $this->formatHealthStatus($result->status),
                $result->getNotificationMessage() . ($remediation ? "\n{$remediation}" : ''),
            ];
        }

        $this->table([
            trans('commands.environment_check.check'),
            trans('commands.environment_check.status'),
            trans('commands.environment_check.details'),
        ], $rows);
    }

    /**
     * Format a health status for colorized console output.
     */
    private function formatHealthStatus(Status $status): string
    {
        return match ($status) {
            Status::ok() => '<fg=green>' . trans('commands.environment_check.passed') . '</>',
            Status::warning() => '<fg=yellow>' . trans('commands.environment_check.warning') . '</>',
            Status::failed(), Status::crashed() => '<fg=red>' . trans('commands.environment_check.failed') . '</>',
            default => (string) $status->value,
        };
    }
}
