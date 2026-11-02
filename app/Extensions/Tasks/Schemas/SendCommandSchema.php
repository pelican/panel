<?php

namespace App\Extensions\Tasks\Schemas;

use App\Enums\SubuserPermission;
use App\Models\Schedule;
use App\Models\Task;
use App\Models\User;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;

final class SendCommandSchema extends TaskSchema
{
    public function getId(): string
    {
        return 'command';
    }

    public function runTask(Task $task): void
    {
        $task->server->send($task->payload);
    }

    public function canCreate(Schedule $schedule, ?User $user = null, ?string $payload = null): bool
    {
        return $user?->can(SubuserPermission::ControlConsole, $schedule->server) ?? true;
    }

    public function getPayloadLabel(): string
    {
        return trans('server/schedule.tasks.actions.command.command');
    }

    /** @return Component[] */
    public function getPayloadForm(): array
    {
        return [
            TextInput::make('payload')
                ->required()
                ->label($this->getPayloadLabel())
                ->default($this->getDefaultPayload()),
        ];
    }
}
