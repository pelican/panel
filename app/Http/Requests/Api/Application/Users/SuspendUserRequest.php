<?php

namespace App\Http\Requests\Api\Application\Users;

use App\Http\Requests\Api\Application\ApplicationApiRequest;
use App\Models\User;
use App\Services\Acl\Api\AdminAcl;

class SuspendUserRequest extends ApplicationApiRequest
{
    protected ?string $resource = User::RESOURCE_NAME;

    protected int $permission = AdminAcl::WRITE;

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:5000'],
            'suspend_servers' => ['sometimes', 'boolean'],
        ];
    }
}
