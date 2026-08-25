<?php

namespace App\Data\Api\Application;

use App\Data\Api\ApiResource;
use App\Data\Api\IncludeContext;
use App\Models\Database;
use App\Models\DatabaseHost;
use App\Models\Node;

final class DatabaseHostData extends ApiResource
{
    /** @var string[] */
    public static array $availableIncludes = [
        'databases',
        'nodes',
    ];

    public function __construct(
        public int $id,
        public string $name,
        public string $host,
        public int $port,
        public string $username,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function getResourceName(): string
    {
        return DatabaseHost::RESOURCE_NAME;
    }

    public static function fromModel(DatabaseHost $model): static
    {
        return new self(
            id: $model->id,
            name: $model->name,
            host: $model->host,
            port: $model->port,
            username: $model->username,
            created_at: $model->created_at->toAtomString(),
            updated_at: $model->updated_at->toAtomString(),
        );
    }

    public static function includes(): array
    {
        return [
            'databases' => function (DatabaseHost $host, IncludeContext $context): array {
                if (!$context->allowsAdmin(Database::RESOURCE_NAME)) {
                    return $context->null();
                }

                $host->loadMissing('databases');

                return $context->collection($host->getRelation('databases'), ServerDatabaseData::class);
            },
            'nodes' => function (DatabaseHost $host, IncludeContext $context): array {
                if (!$context->allowsAdmin(Node::RESOURCE_NAME)) {
                    return $context->null();
                }

                $host->loadMissing('nodes');

                return $context->collection($host->getRelation('nodes'), NodeData::class);
            },
        ];
    }
}
