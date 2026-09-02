<?php

namespace App\Http\Requests\Api\Application\Users;

use App\Http\Requests\Api\Application\ApplicationApiRequest;
use App\Models\User;
use App\Services\Acl\Api\AdminAcl;

class UnsuspendUserRequest extends ApplicationApiRequest
{
    protected ?string $resource = User::RESOURCE_NAME;

    protected int $permission = AdminAcl::WRITE;

    public function rules(): array
    {
        return [
            'unsuspend_servers' => ['sometimes', 'boolean'],
        ];
    }
}
