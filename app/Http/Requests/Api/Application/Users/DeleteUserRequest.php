<?php

namespace App\Http\Requests\Api\Application\Users;

use App\Http\Requests\Api\Application\ApplicationApiRequest;
use App\Http\Requests\Api\Application\Users\Concerns\ProtectsRootAdmins;
use App\Models\User;
use App\Services\Acl\Api\AdminAcl;

class DeleteUserRequest extends ApplicationApiRequest
{
    use ProtectsRootAdmins;

    protected ?string $resource = User::RESOURCE_NAME;

    protected int $permission = AdminAcl::WRITE;

    public function authorize(): bool
    {
        if (!parent::authorize()) {
            return false;
        }

        $this->denyIfTargetIsRootAdmin();

        return true;
    }
}
