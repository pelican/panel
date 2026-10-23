<?php

namespace App\Extensions\Tasks\Schemas;

use App\Extensions\Tasks\TaskSchemaInterface;
use App\Models\Schedule;
use App\Models\User;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Component;

abstract class TaskSchema implements TaskSchemaInterface
{
    public function getName(): string
    {
        return trans('server/schedule.tasks.actions.' . $this->getId() . '.title');
    }

    public function canCreate(Schedule $schedule, ?User $user = null, ?string $payload = null): bool
    {
        return true;
    }

    public function getDefaultPayload(): ?string
    {
        return null;
    }

    public function getPayloadLabel(): ?string
    {
        return null;
    }

    /** @return null|string|string[] */
    public function formatPayload(string $payload): null|string|array
    {
        if (empty($payload)) {
            return null;
        }

        return explode(PHP_EOL, $payload);
    }

    /** @return Component[] */
    public function getPayloadForm(): array
    {
        return [
            Textarea::make('payload')
                ->label($this->getPayloadLabel() ?? trans('server/schedule.tasks.payload'))
                ->default($this->getDefaultPayload())
                ->autosize(),
        ];
    }
}
