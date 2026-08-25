<?php

namespace App\Data\Api\Application;

use App\Data\Api\ApiResource;
use App\Data\Api\IncludeContext;
use App\Models\Role;

final class RoleData extends ApiResource
{
    /** @var string[] */
    public static array $availableIncludes = ['permissions'];

    public function __construct(
        public int $id,
        public string $name,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function getResourceName(): string
    {
        return Role::RESOURCE_NAME;
    }

    public static function fromModel(Role $model): static
    {
        return new self(
            id: $model->id,
            name: $model->name,
            created_at: $model->created_at->toAtomString(),
            updated_at: $model->updated_at->toAtomString(),
        );
    }

    public static function includes(): array
    {
        return [
            'permissions' => function (Role $role, IncludeContext $context): array {
                $role->loadMissing('permissions');

                return $context->collection($role->getRelation('permissions'), RolePermissionData::class);
            },
        ];
    }
}
