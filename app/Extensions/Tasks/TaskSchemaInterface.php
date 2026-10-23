<?php

namespace App\Extensions\Tasks;

use App\Models\Schedule;
use App\Models\Task;
use App\Models\User;
use Filament\Schemas\Components\Component;

interface TaskSchemaInterface
{
    public function getId(): string;

    public function getName(): string;

    public function runTask(Task $task): void;

    /**
     * Whether a task with this action may be created on the schedule. When a user is given their
     * subuser permissions are checked as well, and when a payload is given it is checked too.
     */
    public function canCreate(Schedule $schedule, ?User $user = null, ?string $payload = null): bool;

    public function getDefaultPayload(): ?string;

    public function getPayloadLabel(): ?string;

    /** @return null|string|string[] */
    public function formatPayload(string $payload): null|string|array;

    /** @return Component[] */
    public function getPayloadForm(): array;
}
