<?php

namespace App\Http\Requests\Api\Application\Roles;

use App\Http\Requests\Api\Application\ApplicationApiRequest;
use App\Models\Role;
use App\Services\Acl\Api\AdminAcl;

class StoreRoleRequest extends ApplicationApiRequest
{
    protected ?string $resource = Role::RESOURCE_NAME;

    protected int $permission = AdminAcl::WRITE;

    /**
     * @param  array<array-key, string|string[]>|null  $rules
     * @return array<array-key, string|string[]>
     */
    public function rules(?array $rules = null): array
    {
        return [
            /** Name of the role. */
            'name' => 'required|string',
        ];
    }
}
