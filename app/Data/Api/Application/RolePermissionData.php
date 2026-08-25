<?php

namespace App\Data\Api\Application;

use App\Data\Api\ApiResource;
use Spatie\Permission\Models\Permission;

final class RolePermissionData extends ApiResource
{
    public function __construct(
        public string $name,
        public string $created_at,
        public string $updated_at,
    ) {}

    public static function getResourceName(): string
    {
        return 'permissions';
    }

    public static function fromModel(Permission $model): static
    {
        return new self(
            name: $model->name,
            created_at: $model->created_at->toAtomString(),
            updated_at: $model->updated_at->toAtomString(),
        );
    }
}
