<?php

namespace App\Data\Api\Client;

use App\Data\Api\ApiResource;
use App\Data\Api\IncludeContext;
use App\Enums\SubuserPermission;
use App\Models\Database;

final class DatabaseData extends ApiResource
{
    /** @var string[] */
    public static array $availableIncludes = ['password'];

    /**
     * @param  array{address: string, port: int}  $host
     */
    public function __construct(
        public int $id,
        public array $host,
        public string $name,
        public string $username,
        public ?string $connections_from,
        public ?int $max_connections,
    ) {}

    public static function getResourceName(): string
    {
        return Database::RESOURCE_NAME;
    }

    public static function fromModel(Database $model): static
    {
        $model->loadMissing('host');

        return new self(
            id: $model->id,
            host: [
                'address' => $model->getRelation('host')->host,
                'port' => $model->getRelation('host')->port,
            ],
            name: $model->database,
            username: $model->username,
            connections_from: $model->remote,
            max_connections: $model->max_connections,
        );
    }

    public static function includes(): array
    {
        return [
            'password' => function (Database $database, IncludeContext $context): array {
                if (!$context->allowsClient(SubuserPermission::DatabaseViewPassword->value, $database->server)) {
                    return $context->null();
                }

                return $context->rawItem([
                    'password' => $database->password,
                ], 'database_password');
            },
        ];
    }
}
