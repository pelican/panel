<?php

namespace App\Http\Requests\Api\Application\Users;

class AssignUserRolesRequest extends StoreUserRequest
{
    /** @return array<array-key, string|string[]> */
    public function rules(?array $rules = null): array
    {
        return [
            /** IDs of the roles the user should hold. Roles left out of this list are taken away. */
            'roles' => 'required|array',
            /** ID of a role to assign to the user. */
            'roles.*' => 'integer|exists:roles,id',
        ];
    }
}
