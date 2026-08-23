<?php

namespace App\Http\Requests\Api\Client\Servers\Subusers;

use App\Enums\SubuserPermission;

class StoreSubuserRequest extends SubuserRequest
{
    public function permission(): SubuserPermission
    {
        return SubuserPermission::UserCreate;
    }

    public function rules(): array
    {
        return [
            /** Email address of the person to invite. An account is created for them if they do not have one. */
            'email' => 'required|email|between:1,255',
            /** Permissions the subuser is granted on this server. */
            'permissions' => 'required|array',
            /** A permission key, such as `control.console` or `file.read`. */
            'permissions.*' => 'string',
        ];
    }
}
