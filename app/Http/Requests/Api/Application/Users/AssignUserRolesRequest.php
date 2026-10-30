<?php

namespace App\Http\Requests\Api\Application\Users;

use App\Http\Requests\Api\Application\Users\Concerns\ProtectsRootAdmins;

class AssignUserRolesRequest extends StoreUserRequest
{
    use ProtectsRootAdmins;

    public function authorize(): bool
    {
        if (!parent::authorize()) {
            return false;
        }

        $this->denyIfTargetIsRootAdmin();

        return true;
    }

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
