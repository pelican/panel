<?php

namespace App\Extensions\Tasks\Schemas;

use App\Enums\SubuserPermission;
use App\Models\Schedule;
use App\Models\Server;
use App\Models\Task;
use App\Models\User;
use App\Repositories\Daemon\DaemonServerRepository;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Illuminate\Support\Str;

final class PowerActionSchema extends TaskSchema
{
    public function __construct(private DaemonServerRepository $serverRepository) {}

    public function getId(): string
    {
        return 'power';
    }

    public function runTask(Task $task): void
    {
        $this->serverRepository->setServer($task->server)->power($task->payload);
    }

    public function canCreate(Schedule $schedule, ?User $user = null, ?string $payload = null): bool
    {
        if (!is_null($payload)) {
            return $this->canSendSignal($schedule->server, $user, $payload);
        }

        return collect(['start', 'stop', 'restart'])->contains(fn (string $signal) => $this->canSendSignal($schedule->server, $user, $signal));
    }

    private function canSendSignal(Server $server, ?User $user, string $signal): bool
    {
        $permission = SubuserPermission::fromPowerSignal($signal);
        if (!$permission) {
            return false;
        }

        return $user?->can($permission, $server) ?? true;
    }

    public function getDefaultPayload(): string
    {
        return 'restart';
    }

    public function getPayloadLabel(): string
    {
        return trans('server/schedule.tasks.actions.power.action');
    }

    public function formatPayload(string $payload): string
    {
        return Str::ucfirst($payload);
    }

    /** @return Component[] */
    public function getPayloadForm(): array
    {
        return [
            Select::make('payload')
                ->label($this->getPayloadLabel())
                ->required()
                ->options([
                    'start' => trans('server/schedule.tasks.actions.power.start'),
                    'restart' => trans('server/schedule.tasks.actions.power.restart'),
                    'stop' => trans('server/schedule.tasks.actions.power.stop'),
                    'kill' => trans('server/schedule.tasks.actions.power.kill'),
                ])
                ->disableOptionWhen(function (string $value) {
                    /** @var Server|null $server */
                    $server = Filament::getTenant();

                    return $server && !$this->canSendSignal($server, user(), $value);
                })
                ->selectablePlaceholder(false)
                ->default($this->getDefaultPayload()),
        ];
    }
}
