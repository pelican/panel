<?php

namespace App\Data\Api\Application;

use App\Data\Api\ApiResource;
use App\Data\Api\IncludeContext;
use App\Models\Database;
use App\Models\DatabaseHost;

final class ServerDatabaseData extends ApiResource
{
    /** @var string[] */
    public static array $availableIncludes = ['password', 'host'];

    public function __construct(
        public int $id,
        public int $server,
        public int $host,
        public string $database,
        public string $username,
        public string $remote,
        public ?int $max_connections,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function getResourceName(): string
    {
        return Database::RESOURCE_NAME;
    }

    public static function fromModel(Database $model): static
    {
        return new self(
            id: $model->id,
            server: $model->server_id,
            host: $model->database_host_id,
            database: $model->database,
            username: $model->username,
            remote: $model->remote,
            max_connections: $model->max_connections,
            created_at: $model->created_at->toAtomString(),
            updated_at: $model->updated_at->toAtomString(),
        );
    }

    public static function includes(): array
    {
        return [
            'password' => function (Database $database, IncludeContext $context): array {
                return $context->rawItem([
                    'password' => $database->password,
                ], 'database_password');
            },
            'host' => function (Database $database, IncludeContext $context): array {
                if (!$context->allowsAdmin(DatabaseHost::RESOURCE_NAME)) {
                    return $context->null();
                }

                $database->loadMissing('host');

                return $context->item($database->getRelation('host'), DatabaseHostData::class);
            },
        ];
    }
}
