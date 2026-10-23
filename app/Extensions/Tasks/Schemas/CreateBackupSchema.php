<?php

namespace App\Extensions\Tasks\Schemas;

use App\Enums\SubuserPermission;
use App\Models\Schedule;
use App\Models\Task;
use App\Models\User;
use App\Services\Backups\InitiateBackupService;

final class CreateBackupSchema extends TaskSchema
{
    public function __construct(private InitiateBackupService $initiateService) {}

    public function getId(): string
    {
        return 'backup';
    }

    public function runTask(Task $task): void
    {
        $this->initiateService->setIsScheduled(true)->setIgnoredFiles(explode(PHP_EOL, $task->payload))->handle($task->server, null, true);
    }

    public function canCreate(Schedule $schedule, ?User $user = null, ?string $payload = null): bool
    {
        return $schedule->server->backup_limit > 0 && ($user?->can(SubuserPermission::BackupCreate, $schedule->server) ?? true);
    }

    public function getPayloadLabel(): string
    {
        return trans('server/schedule.tasks.actions.backup.files_to_ignore');
    }
}
