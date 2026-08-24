<?php

namespace App\Data\Api\Client;

use App\Data\Api\ApiResource;
use App\Data\Api\IncludeContext;
use App\Models\Schedule;

final class ScheduleData extends ApiResource
{
    /** @var string[] */
    public static array $availableIncludes = ['tasks'];

    /** @var string[] */
    public static array $defaultIncludes = ['tasks'];

    /**
     * @param  array{day_of_week: string, day_of_month: string, month: string, hour: string, minute: string}  $cron
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $cron,
        public bool $is_active,
        public bool $is_processing,
        public bool $only_when_online,
        public ?string $last_run_at,
        public ?string $next_run_at,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function getResourceName(): string
    {
        return Schedule::RESOURCE_NAME;
    }

    public static function fromModel(Schedule $model): static
    {
        return new self(
            id: $model->id,
            name: $model->name,
            cron: [
                'day_of_week' => $model->cron_day_of_week,
                'day_of_month' => $model->cron_day_of_month,
                'month' => $model->cron_month,
                'hour' => $model->cron_hour,
                'minute' => $model->cron_minute,
            ],
            is_active: $model->is_active,
            is_processing: $model->is_processing,
            only_when_online: $model->only_when_online,
            last_run_at: $model->last_run_at?->toAtomString(),
            next_run_at: $model->next_run_at?->toAtomString(),
            created_at: $model->created_at->toAtomString(),
            updated_at: $model->updated_at->toAtomString(),
        );
    }

    public static function includes(): array
    {
        return [
            'tasks' => function (Schedule $model, IncludeContext $context): array {
                return $context->collection($model->tasks, TaskData::class);
            },
        ];
    }
}
