<?php

namespace App\Data\Api\Application;

use App\Data\Api\ApiResource;
use App\Data\Api\IncludeContext;
use App\Models\Egg;
use App\Models\EggVariable;
use App\Models\Server;
use Illuminate\Support\Arr;

final class EggData extends ApiResource
{
    /** @var string[] */
    public static array $availableIncludes = [
        'servers',
        'variables',
    ];

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $script
     */
    public function __construct(
        public int $id,
        public string $uuid,
        public string $name,
        public ?string $author,
        public ?string $description,
        public ?string $icon,
        public mixed $features,
        public mixed $tags,
        public string $docker_image,
        public mixed $docker_images,
        public array $config,
        public string $startup,
        public mixed $startup_commands,
        public array $script,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function getResourceName(): string
    {
        return Egg::RESOURCE_NAME;
    }

    public static function fromModel(Egg $model): static
    {
        $model->loadMissing('configFrom');

        $files = json_decode($model->inherit_config_files ?: '{}', true, 512, JSON_THROW_ON_ERROR);

        $model->loadMissing('scriptFrom');

        return new self(
            id: $model->id,
            uuid: $model->uuid,
            name: $model->name,
            author: $model->author,
            description: $model->description,
            icon: $model->icon,
            features: $model->features,
            tags: $model->tags,
            docker_image: Arr::first($model->docker_images, default: ''), // deprecated, use docker_images
            docker_images: $model->docker_images,
            config: [
                'files' => $files,
                'startup' => json_decode($model->inherit_config_startup ?: '{}', true),
                'stop' => $model->inherit_config_stop,
                'logs' => json_decode($model->inherit_config_logs ?: '{}', true),
                'file_denylist' => $model->inherit_file_denylist,
                'extends' => $model->config_from,
            ],
            startup: Arr::first($model->startup_commands, default: ''), // deprecated, use startup_commands
            startup_commands: $model->startup_commands,
            script: [
                'privileged' => $model->script_is_privileged,
                'install' => $model->copy_script_install,
                'entry' => $model->copy_script_entry,
                'container' => $model->copy_script_container,
                'extends' => $model->copy_script_from,
            ],
            created_at: self::formatTimestamp($model->created_at),
            updated_at: self::formatTimestamp($model->updated_at),
        );
    }

    public static function includes(): array
    {
        return [
            'servers' => function (Egg $egg, IncludeContext $context): array {
                if (!$context->allowsAdmin(Server::RESOURCE_NAME)) {
                    return $context->null();
                }

                $egg->loadMissing('servers');

                return $context->collection($egg->getRelation('servers'), ServerData::class);
            },
            'variables' => function (Egg $egg, IncludeContext $context): array {
                if (!$context->allowsAdmin(Egg::RESOURCE_NAME)) {
                    return $context->null();
                }

                $egg->loadMissing('variables');

                return $context->collection($egg->getRelation('variables'), EggVariableData::class, EggVariable::RESOURCE_NAME);
            },
        ];
    }
}
