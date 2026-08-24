<?php

namespace App\Data\Api\Application;

use App\Data\Api\ApiResource;
use App\Data\Api\IncludeContext;
use App\Models\Server;
use App\Models\Subuser;
use App\Models\User;

final class SubuserData extends ApiResource
{
    /** @var string[] */
    public static array $availableIncludes = ['user', 'server'];

    public function __construct(
        public int $id,
        public int $user_id,
        public int $server_id,
        public mixed $permissions,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function getResourceName(): string
    {
        return Subuser::RESOURCE_NAME;
    }

    public static function fromModel(Subuser $model): static
    {
        return new self(
            id: $model->id,
            user_id: $model->user_id,
            server_id: $model->server_id,
            permissions: $model->permissions,
            created_at: self::formatTimestamp($model->created_at),
            updated_at: self::formatTimestamp($model->updated_at),
        );
    }

    public static function includes(): array
    {
        return [
            'user' => function (Subuser $subuser, IncludeContext $context): array {
                if (!$context->allowsAdmin(User::RESOURCE_NAME)) {
                    return $context->null();
                }

                $subuser->loadMissing('user');

                return $context->item($subuser->getRelation('user'), UserData::class);
            },
            'server' => function (Subuser $subuser, IncludeContext $context): array {
                if (!$context->allowsAdmin(Server::RESOURCE_NAME)) {
                    return $context->null();
                }

                $subuser->loadMissing('server');

                return $context->item($subuser->getRelation('server'), ServerData::class);
            },
        ];
    }
}
