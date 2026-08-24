<?php

namespace App\Data\Api\Application;

use App\Data\Api\ApiResource;
use App\Data\Api\IncludeContext;
use App\Models\Allocation;
use App\Models\Database;
use App\Models\Egg;
use App\Models\Node;
use App\Models\Server;
use App\Models\User;
use App\Services\Servers\EnvironmentService;

final class ServerData extends ApiResource
{
    /** @var string[] */
    public static array $availableIncludes = [
        'allocations',
        'user',
        'subusers',
        'egg',
        'variables',
        'node',
        'databases',
    ];

    /**
     * @param  array<string, mixed>  $limits
     * @param  array<string, mixed>  $feature_limits
     * @param  array<string, mixed>  $container
     */
    public function __construct(
        public int $id,
        public ?string $external_id,
        public string $uuid,
        public string $identifier,
        public string $name,
        public ?string $description,
        public mixed $status,
        public bool $suspended,
        public array $limits,
        public array $feature_limits,
        public int $user,
        public int $node,
        public ?int $allocation,
        public int $egg,
        public array $container,
        public string $updated_at,
        public string $created_at,
    ) {}

    public static function getResourceName(): string
    {
        return Server::RESOURCE_NAME;
    }

    public static function fromModel(Server $model, EnvironmentService $environmentService): static
    {
        return new self(
            id: $model->getKey(),
            external_id: $model->external_id,
            uuid: $model->uuid,
            identifier: $model->uuid_short,
            name: $model->name,
            description: $model->description,
            status: $model->status,
            // This field is deprecated, please use "status".
            suspended: $model->isSuspended(),
            limits: [
                'memory' => $model->memory,
                'swap' => $model->swap,
                'disk' => $model->disk,
                'io' => $model->io,
                'cpu' => $model->cpu,
                'threads' => $model->threads,
                // This field is deprecated, please use "oom_killer".
                'oom_disabled' => !$model->oom_killer,
                'oom_killer' => $model->oom_killer,
            ],
            feature_limits: [
                'databases' => $model->database_limit,
                'allocations' => $model->allocation_limit,
                'backups' => $model->backup_limit,
            ],
            user: $model->owner_id,
            node: $model->node_id,
            allocation: $model->allocation_id,
            egg: $model->egg_id,
            container: [
                'startup_command' => $model->startup,
                'image' => $model->image,
                // This field is deprecated, please use "status".
                'installed' => $model->isInstalled() ? 1 : 0,
                'environment' => $environmentService->handle($model),
            ],
            updated_at: self::formatTimestamp($model->updated_at),
            created_at: self::formatTimestamp($model->created_at),
        );
    }

    public static function includes(): array
    {
        return [
            'allocations' => function (Server $server, IncludeContext $context): array {
                if (!$context->allowsAdmin(Allocation::RESOURCE_NAME)) {
                    return $context->null();
                }

                $server->loadMissing('allocations');

                return $context->collection($server->getRelation('allocations'), AllocationData::class);
            },
            'user' => function (Server $server, IncludeContext $context): array {
                if (!$context->allowsAdmin(User::RESOURCE_NAME)) {
                    return $context->null();
                }

                $server->loadMissing('user');

                return $context->item($server->getRelation('user'), UserData::class);
            },
            'subusers' => function (Server $server, IncludeContext $context): array {
                if (!$context->allowsAdmin(User::RESOURCE_NAME)) {
                    return $context->null();
                }

                $server->loadMissing('subusers');

                return $context->collection($server->getRelation('subusers'), SubuserData::class, 'subuser');
            },
            'egg' => function (Server $server, IncludeContext $context): array {
                if (!$context->allowsAdmin(Egg::RESOURCE_NAME)) {
                    return $context->null();
                }

                $server->loadMissing('egg');

                return $context->item($server->getRelation('egg'), EggData::class);
            },
            'variables' => function (Server $server, IncludeContext $context): array {
                if (!$context->allowsAdmin(Server::RESOURCE_NAME)) {
                    return $context->null();
                }

                $server->loadMissing('variables');

                return $context->collection($server->getRelation('variables'), ServerVariableData::class, 'server_variable');
            },
            'node' => function (Server $server, IncludeContext $context): array {
                if (!$context->allowsAdmin(Node::RESOURCE_NAME)) {
                    return $context->null();
                }

                $server->loadMissing('node');

                return $context->item($server->getRelation('node'), NodeData::class);
            },
            'databases' => function (Server $server, IncludeContext $context): array {
                if (!$context->allowsAdmin(Database::RESOURCE_NAME)) {
                    return $context->null();
                }

                $server->loadMissing('databases');

                return $context->collection($server->getRelation('databases'), ServerDatabaseData::class, 'databases');
            },
        ];
    }
}
