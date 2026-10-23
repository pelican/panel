<?php

namespace App\Extensions\Tasks\Schemas;

use App\Enums\SubuserPermission;
use App\Models\Schedule;
use App\Models\Task;
use App\Models\User;
use App\Services\Files\DeleteFilesService;

final class DeleteFilesSchema extends TaskSchema
{
    public function __construct(private DeleteFilesService $deleteFilesService) {}

    public function getId(): string
    {
        return 'delete_files';
    }

    public function runTask(Task $task): void
    {
        $this->deleteFilesService->handle($task->server, explode(PHP_EOL, $task->payload));
    }

    public function canCreate(Schedule $schedule, ?User $user = null, ?string $payload = null): bool
    {
        return $user?->can(SubuserPermission::FileDelete, $schedule->server) ?? true;
    }

    public function getPayloadLabel(): string
    {
        return trans('server/schedule.tasks.actions.delete_files.files_to_delete');
    }
}
