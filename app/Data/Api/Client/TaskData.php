<?php

namespace App\Data\Api\Client;

use App\Data\Api\ApiResource;
use App\Models\Task;

final class TaskData extends ApiResource
{
    public function __construct(
        public int $id,
        public int $sequence_id,
        public string $action,
        public ?string $payload,
        public int $time_offset,
        public bool $is_queued,
        public bool $continue_on_failure,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function getResourceName(): string
    {
        return Task::RESOURCE_NAME;
    }

    public static function fromModel(Task $model): static
    {
        return new self(
            id: $model->id,
            sequence_id: $model->sequence_id,
            action: $model->action,
            payload: $model->payload,
            time_offset: $model->time_offset,
            is_queued: $model->is_queued,
            continue_on_failure: $model->continue_on_failure,
            created_at: $model->created_at->toAtomString(),
            updated_at: $model->updated_at->toAtomString(),
        );
    }
}
